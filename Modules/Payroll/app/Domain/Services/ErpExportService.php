<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\ErpAdapterPayload;
use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Domain\Adapters\ErpAccountingAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ErpExportService
{
    public function __construct(private ConnectionInterface $db, private ErpAccountingAdapter $adapter) {}

    public function create(int $companyId, int $connectionId, int $journalId, int $actorId, string $idempotencyKey): ErpExportResult
    {
        return $this->db->transaction(function () use ($companyId, $connectionId, $journalId, $actorId, $idempotencyKey): ErpExportResult {
            $existing = $this->db->table('erp_exports')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return new ErpExportResult((int) $existing, $this->status((int) $existing, $companyId), true);
            }
            $connection = $this->db->table('erp_connections')->where('company_id', $companyId)->where('id', $connectionId)->where('status', 'active')->lockForUpdate()->first();
            $journal = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('id', $journalId)->whereIn('status', ['internally_posted', 'exported'])->lockForUpdate()->first();
            if (! is_object($connection) || ! is_object($journal)) {
                throw ValidationException::withMessages(['journal' => 'ERP_CONNECTION_OR_POSTED_JOURNAL_NOT_FOUND']);
            }
            $intent = $companyId.':'.$connectionId.':'.$journalId;
            $active = $this->db->table('erp_exports')->where('active_intent_key', $intent)->lockForUpdate()->first();
            if (is_object($active)) {
                return new ErpExportResult($this->i($active, 'id'), $this->s($active, 'status'), true);
            }
            $postingDate = $this->s($journal, 'posting_date');
            $lines = $this->db->table('payroll_accounting_journal_lines as l')->join('erp_mapping_rules as m', function (JoinClause $join) use ($connectionId): void {
                $join->on('m.source_account_code', '=', 'l.account_code_snapshot')->where('m.erp_connection_id', $connectionId)->where('m.status', 'active');
            })->where('l.company_id', $companyId)->where('l.journal_id', $journalId)->whereDate('m.effective_from', '<=', $postingDate)->where(fn (Builder $query): Builder => $query->whereNull('m.effective_to')->orWhereDate('m.effective_to', '>=', $postingDate))->orderBy('l.line_number')->select(['l.*', 'm.external_account_code'])->get();
            if ($lines->isEmpty() || $this->i($journal, 'line_count') !== $lines->count()) {
                throw ValidationException::withMessages(['mapping' => 'ERP_MAPPING_INCOMPLETE']);
            }
            $snapshot = ['journal_id' => $journalId, 'journal_uuid' => $this->s($journal, 'uuid'), 'journal_number' => $this->s($journal, 'journal_number'), 'calculation_version' => $this->i($journal, 'calculation_version'), 'currency' => $this->s($journal, 'currency'), 'total_debit_minor' => $this->i($journal, 'total_debit_minor'), 'total_credit_minor' => $this->i($journal, 'total_credit_minor'), 'line_count' => $lines->count()];
            $checksum = hash('sha256', json_encode([$snapshot, $lines->map(fn (object $l): array => $this->itemPayload($l))->all()], JSON_THROW_ON_ERROR));
            $id = (int) $this->db->table('erp_exports')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'erp_connection_id' => $connectionId, 'accounting_journal_id' => $journalId, 'provider' => $this->s($connection, 'provider'), 'status' => 'draft', 'export_version' => 1, 'idempotency_key' => $idempotencyKey, 'active_intent_key' => $intent, 'payload_checksum' => $checksum, 'journal_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                $p = $this->itemPayload($line);
                $this->db->table('erp_export_items')->insert(['company_id' => $companyId, 'erp_export_id' => $id, 'accounting_journal_line_id' => $this->i($line, 'id'), 'line_number' => $p['line_number'], 'source_account_code' => $this->s($line, 'account_code_snapshot'), 'external_account_code' => $p['account'], 'description' => $p['description'], 'debit_minor' => $p['debit_minor'], 'credit_minor' => $p['credit_minor'], 'currency' => $p['currency'], 'dimension_snapshot' => json_encode(['cost_center' => $p['cost_center'], 'organization_unit' => $p['organization_unit']], JSON_THROW_ON_ERROR), 'item_checksum' => hash('sha256', json_encode($p, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->log($companyId, $id, $actorId, 'created', null, 'draft');

            return new ErpExportResult($id, 'draft');
        }, 3);
    }

    public function validate(int $companyId, int $id, int $actorId): ErpExportResult
    {
        return $this->transition($companyId, $id, $actorId, 'draft', 'validated', function (object $e) use ($actorId): array {
            if ($this->i($e, 'created_by') === $actorId) {
                throw ValidationException::withMessages(['actor' => 'ERP_MAKER_CANNOT_VALIDATE']);
            }
            $items = $this->db->table('erp_export_items')->where('erp_export_id', $this->i($e, 'id'));
            if (! $items->exists() || (int) $items->sum('debit_minor') !== (int) $items->sum('credit_minor')) {
                throw ValidationException::withMessages(['balance' => 'ERP_EXPORT_NOT_BALANCED']);
            }

            return ['validated_by' => $actorId, 'validated_at' => now()];
        });
    }

    public function execute(int $companyId, int $id, int $actorId): ErpExportResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId): ErpExportResult {
            $e = $this->export($companyId, $id, true);
            $status = $this->s($e, 'status');
            if ($status === 'exported') {
                return new ErpExportResult($id, $status, true);
            }
            if (! in_array($status, ['validated', 'failed'], true)) {
                throw ValidationException::withMessages(['status' => 'ERP_EXPORT_INVALID_EXECUTE_TRANSITION']);
            }
            if ($this->i($e, 'validated_by') === $actorId) {
                throw ValidationException::withMessages(['actor' => 'ERP_VALIDATOR_CANNOT_EXECUTE']);
            }
            $items = $this->db->table('erp_export_items')->where('company_id', $companyId)->where('erp_export_id', $id)->orderBy('line_number')->get();
            $snapshot = json_decode($this->s($e, 'journal_snapshot'), true, 512, JSON_THROW_ON_ERROR);
            $reference = $this->nullable($e, 'external_reference') ?? (string) Str::uuid();
            $lines = array_values($items->map(fn (object $i): array => ['line_number' => $this->i($i, 'line_number'), 'account' => $this->s($i, 'external_account_code'), 'description' => $this->s($i, 'description'), 'debit_minor' => $this->i($i, 'debit_minor'), 'credit_minor' => $this->i($i, 'credit_minor'), 'currency' => $this->s($i, 'currency'), 'cost_center' => $this->dimension($i, 'cost_center'), 'organization_unit' => $this->dimension($i, 'organization_unit')])->all());
            $this->db->table('erp_exports')->where('id', $id)->update(['status' => 'exporting', 'attempt_count' => $this->i($e, 'attempt_count') + 1, 'last_attempt_at' => now(), 'updated_at' => now()]);
            $artifact = $this->adapter->export(new ErpAdapterPayload($reference, is_array($snapshot) && is_string($snapshot['journal_number'] ?? null) ? $snapshot['journal_number'] : '', $lines));
            $path = 'erp/'.$companyId.'/'.$this->s($e, 'uuid').'/v'.$this->i($e, 'export_version').'.'.$artifact->extension;
            Storage::disk('local')->put($path, $artifact->bytes);
            $this->db->table('erp_exports')->where('id', $id)->update(['status' => 'exported', 'external_reference' => $reference, 'artifact_disk' => 'local', 'artifact_path' => $path, 'artifact_name' => 'erp-export-'.$this->s($e, 'uuid').'.'.$artifact->extension, 'artifact_mime' => $artifact->mime, 'artifact_size' => strlen($artifact->bytes), 'artifact_checksum' => hash('sha256', $artifact->bytes), 'executed_by' => $actorId, 'exported_at' => now(), 'failure_code' => null, 'failure_message' => null, 'updated_at' => now()]);
            $this->log($companyId, $id, $actorId, 'executed', $status, 'exported');

            return new ErpExportResult($id, 'exported');
        }, 3);
    }

    public function retry(int $companyId, int $id, int $actorId): ErpExportResult
    {
        return $this->execute($companyId, $id, $actorId);
    }

    public function reverse(int $companyId, int $id, int $actorId): ErpExportResult
    {
        return $this->transition($companyId, $id, $actorId, 'exported', 'reversed', fn (): array => ['reversed_by' => $actorId, 'reversed_at' => now(), 'active_intent_key' => null]);
    }

    public function download(int $companyId, int $id, int $actorId): StreamedResponse
    {
        $export = $this->export($companyId, $id, false);
        $result = 'blocked';
        try {
            if ($this->s($export, 'status') !== 'exported' || $this->s($export, 'artifact_disk') !== 'local' || $this->s($export, 'artifact_mime') !== 'text/csv') {
                throw ValidationException::withMessages(['artifact' => 'ERP_ARTIFACT_NOT_DOWNLOADABLE']);
            }
            $path = $this->s($export, 'artifact_path');
            $bytes = Storage::disk('local')->get($path);
            if (! is_string($bytes) || strlen($bytes) !== $this->i($export, 'artifact_size') || ! hash_equals($this->s($export, 'artifact_checksum'), hash('sha256', $bytes)) || ! str_starts_with($bytes, "\xEF\xBB\xBFReference,Journal,Line,")) {
                throw ValidationException::withMessages(['artifact' => 'ERP_ARTIFACT_INTEGRITY_FAILED']);
            }
            $result = 'success';
            $name = $this->s($export, 'artifact_name');

            return response()->streamDownload(static function () use ($bytes): void {
                echo $bytes;
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
        } finally {
            $this->db->table('erp_access_logs')->insert(['company_id' => $companyId, 'erp_export_id' => $id, 'actor_id' => $actorId, 'action' => 'erp_artifact_download', 'result' => $result, 'accessed_at' => now()]);
        }
    }

    /** @param callable(object):array<string,int|string|\DateTimeInterface|null> $extra */
    private function transition(int $companyId, int $id, int $actorId, string $from, string $to, callable $extra): ErpExportResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId, $from, $to, $extra): ErpExportResult {
            $e = $this->export($companyId, $id, true);
            if ($this->s($e, 'status') === $to) {
                return new ErpExportResult($id, $to, true);
            } if ($this->s($e, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'ERP_EXPORT_INVALID_TRANSITION']);
            } $this->db->table('erp_exports')->where('id', $id)->update(['status' => $to, 'updated_at' => now()] + $extra($e));
            $this->log($companyId, $id, $actorId, $to, $from, $to);

            return new ErpExportResult($id, $to);
        }, 3);
    }

    private function export(int $companyId, int $id, bool $lock): object
    {
        $q = $this->db->table('erp_exports')->where('company_id', $companyId)->where('id', $id);
        if ($lock) {
            $q->lockForUpdate();
        } $e = $q->first();
        abort_unless(is_object($e), 404);

        return $e;
    }

    private function status(int $id, int $companyId): string
    {
        return $this->s($this->export($companyId, $id, false), 'status');
    }

    private function log(int $companyId, int $id, int $actor, string $action, ?string $from, ?string $to): void
    {
        $this->db->table('erp_export_logs')->insert(['company_id' => $companyId, 'erp_export_id' => $id, 'actor_id' => $actor, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'created_at' => now()]);
    }

    /** @return array{line_number:int,account:string,description:string,debit_minor:int,credit_minor:int,currency:string,cost_center:string,organization_unit:string} */
    private function itemPayload(object $l): array
    {
        return ['line_number' => $this->i($l, 'line_number'), 'account' => $this->s($l, 'external_account_code'), 'description' => $this->s($l, 'description'), 'debit_minor' => $this->i($l, 'debit_minor'), 'credit_minor' => $this->i($l, 'credit_minor'), 'currency' => $this->s($l, 'currency'), 'cost_center' => $this->nullable($l, 'cost_center_code_snapshot') ?? '', 'organization_unit' => $this->nullable($l, 'organization_unit_code_snapshot') ?? ''];
    }

    private function dimension(object $i, string $key): string
    {
        $v = json_decode($this->s($i, 'dimension_snapshot'), true);

        return is_array($v) && is_string($v[$key] ?? null) ? $v[$key] : '';
    }

    private function i(object $o, string $k): int
    {
        $v = $o->{$k} ?? 0;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function s(object $o, string $k): string
    {
        $v = $o->{$k} ?? '';

        return is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
    }

    private function nullable(object $o, string $k): ?string
    {
        $v = $o->{$k} ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }
}
