<?php

namespace Modules\Payroll\Domain\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\AnnualTaxDocumentData;
use Modules\Payroll\Application\DTOs\AnnualTaxDownloadData;
use Modules\Payroll\Application\DTOs\AnnualTaxDownstreamResult;
use stdClass;

final readonly class PayrollAnnualTaxDeliveryService
{
    private const DISK = 'local';

    private const MIME = 'application/pdf';

    public function __construct(private ConnectionInterface $db, private FilesystemManager $files) {}

    public function generateStatement(int $companyId, int $reconciliationId, int $actorId): AnnualTaxDocumentData
    {
        return $this->db->transaction(function () use ($companyId, $reconciliationId, $actorId): AnnualTaxDocumentData {
            $row = $this->reconciliation($companyId, $reconciliationId, true);
            $this->finalized($row);
            $current = $this->db->table('payroll_annual_tax_documents')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $reconciliationId)->where('document_type', 'annual_statement')->where('is_current', true)->lockForUpdate()->first();
            if ($current instanceof stdClass && $this->validStoredDocument($current)) {
                return $this->document($current, true);
            }
            $version = $current instanceof stdClass ? $this->integer($current, 'document_version') + 1 : 1;
            if ($current instanceof stdClass) {
                $this->db->table('payroll_annual_tax_documents')->where('id', $this->integer($current, 'id'))->update(['is_current' => false, 'updated_at' => now()]);
            }
            $sources = $this->db->table('payroll_annual_tax_sources')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $reconciliationId)->orderBy('source_sort_key')->get()->map(static fn (object $source): array => (array) $source)->all();
            $html = Pdf::loadView('payroll::annual-tax.statement', ['reconciliation' => (array) $row, 'company' => $this->jsonObject($row, 'company_tax_snapshot'), 'employee' => $this->jsonObject($row, 'taxpayer_snapshot'), 'sources' => $sources])->setPaper('a4')->output();
            if (! str_starts_with($html, '%PDF-')) {
                throw ValidationException::withMessages(['statement' => 'Generated Annual Tax statement is not a valid PDF.']);
            }
            $uuid = (string) Str::uuid();
            $path = 'payroll/annual-tax/'.$companyId.'/'.$reconciliationId.'/'.$uuid.'.pdf';
            if (! $this->files->disk(self::DISK)->put($path, $html)) {
                throw ValidationException::withMessages(['statement' => 'Private Annual Tax statement storage failed.']);
            }
            $checksum = hash('sha256', $html);
            $id = (int) $this->db->table('payroll_annual_tax_documents')->insertGetId(['annual_tax_reconciliation_id' => $reconciliationId, 'company_id' => $companyId, 'employee_id' => $this->integer($row, 'employee_id'), 'document_type' => 'annual_statement', 'document_version' => $version, 'is_current' => true, 'storage_disk' => self::DISK, 'storage_path' => $path, 'file_name' => $uuid.'.pdf', 'mime_type' => self::MIME, 'file_size' => strlen($html), 'checksum' => $checksum, 'source_checksum' => $this->string($row, 'source_checksum'), 'generated_by' => $actorId, 'generated_at' => now(), 'generation_version' => $this->integer($row, 'calculation_version'), 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $reconciliationId)->where('intent_type', 'annual_statement')->update(['status' => 'completed', 'annual_tax_document_id' => $id, 'attempt_count' => $this->db->raw('attempt_count + 1'), 'last_attempt_at' => now(), 'completed_at' => now(), 'updated_at' => now()]);
            $this->log($row, $id, $actorId, 'generate', 'success');

            return new AnnualTaxDocumentData($id, $reconciliationId, $version, $uuid.'.pdf', self::MIME, strlen($html), $checksum, false);
        }, 3);
    }

    public function download(int $companyId, int $reconciliationId, int $documentId, int $actorId, ?string $ip, ?string $userAgent): AnnualTaxDownloadData
    {
        $row = $this->reconciliation($companyId, $reconciliationId);
        $document = $this->db->table('payroll_annual_tax_documents')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $reconciliationId)->where('id', $documentId)->first();
        if (! $document instanceof stdClass) {
            abort(404);
        }
        if (! $this->validStoredDocument($document)) {
            $this->log($row, $documentId, $actorId, 'download', 'integrity_failed', $ip, $userAgent);
            throw ValidationException::withMessages(['statement' => 'Annual Tax statement integrity verification failed.']);
        }
        $bytes = $this->files->disk($this->string($document, 'storage_disk'))->get($this->string($document, 'storage_path'));
        if (! is_string($bytes)) {
            throw ValidationException::withMessages(['statement' => 'Annual Tax statement cannot be read from private storage.']);
        }
        $this->log($row, $documentId, $actorId, 'download', 'success', $ip, $userAgent);

        return new AnnualTaxDownloadData($bytes, $this->string($document, 'file_name'), self::MIME, strlen($bytes));
    }

    public function paymentIntent(int $companyId, int $id, int $actorId): AnnualTaxDownstreamResult
    {
        return $this->executeIntent($companyId, $id, $actorId, 'additional_tax_recovery', 'payable');
    }

    public function refundIntent(int $companyId, int $id, int $actorId): AnnualTaxDownstreamResult
    {
        return $this->executeIntent($companyId, $id, $actorId, 'tax_refund_payment', 'refund');
    }

    public function settlementJournal(int $companyId, int $id, int $actorId): AnnualTaxDownstreamResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId): AnnualTaxDownstreamResult {
            $row = $this->reconciliation($companyId, $id, true);
            $this->finalized($row);
            if ($this->string($row, 'result_type') === 'balanced') {
                return new AnnualTaxDownstreamResult($id, 'accounting_journal', 'not_applicable', null, true);
            }
            $version = $this->integer($row, 'calculation_version');
            $existing = $this->db->table('payroll_annual_tax_settlement_journals')->where('annual_tax_reconciliation_id', $id)->where('calculation_version', $version)->first();
            if ($existing instanceof stdClass) {
                return new AnnualTaxDownstreamResult($id, 'accounting_journal', 'completed', $this->integer($existing, 'id'), true);
            }
            $amount = max($this->integer($row, 'additional_tax_payable_minor'), $this->integer($row, 'tax_refund_minor'));
            $components = $this->db->table('payroll_annual_tax_components')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $id)->orderBy('id')->get(['category', 'component_code', 'amount_minor', 'currency', 'source_calculation_version'])->map(static fn (object $component): array => (array) $component)->all();
            $journalId = (int) $this->db->table('payroll_annual_tax_settlement_journals')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'annual_tax_reconciliation_id' => $id, 'calculation_version' => $version, 'source_type' => 'annual_tax_reconciliation', 'component_snapshot' => json_encode($components, JSON_THROW_ON_ERROR), 'currency' => $this->string($row, 'currency'), 'total_debit_minor' => $amount, 'total_credit_minor' => $amount, 'status' => 'created', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $trace = json_encode(['reconciliation_id' => $id, 'calculation_version' => $version, 'source_checksum' => $this->string($row, 'source_checksum')], JSON_THROW_ON_ERROR);
            $result = $this->string($row, 'result_type');
            $roles = $result === 'refund' ? [['tax_refund_receivable', $amount, 0], ['tax_liability_adjustment', 0, $amount]] : [['tax_liability_adjustment', $amount, 0], ['tax_payable', 0, $amount]];
            foreach ($roles as $index => [$role, $debit, $credit]) {
                $this->db->table('payroll_annual_tax_settlement_journal_lines')->insert(['company_id' => $companyId, 'settlement_journal_id' => $journalId, 'line_number' => $index + 1, 'account_role' => $role, 'debit_minor' => $debit, 'credit_minor' => $credit, 'currency' => $this->string($row, 'currency'), 'source_trace' => $trace, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->completeIntent($id, 'accounting_journal', ['settlement_journal_id' => $journalId]);

            return new AnnualTaxDownstreamResult($id, 'accounting_journal', 'completed', $journalId, false);
        }, 3);
    }

    /** @return array{AnnualTaxDownstreamResult,AnnualTaxDownstreamResult,AnnualTaxDownstreamResult,AnnualTaxDocumentData} */
    public function retry(int $companyId, int $id, int $actorId): array
    {
        return [$this->paymentIntent($companyId, $id, $actorId), $this->refundIntent($companyId, $id, $actorId), $this->settlementJournal($companyId, $id, $actorId), $this->generateStatement($companyId, $id, $actorId)];
    }

    private function executeIntent(int $companyId, int $id, int $actorId, string $type, string $expectedResult): AnnualTaxDownstreamResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId, $type, $expectedResult): AnnualTaxDownstreamResult {
            $row = $this->reconciliation($companyId, $id, true);
            $this->finalized($row);
            $intent = $this->db->table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->where('intent_type', $type)->lockForUpdate()->first();
            if ($this->string($row, 'result_type') !== $expectedResult) {
                return new AnnualTaxDownstreamResult($id, $type, 'not_applicable', null, true);
            }
            if ($intent instanceof stdClass && $this->string($intent, 'status') === 'completed') {
                $resource = $type === 'tax_refund_payment' ? $this->nullableInteger($intent, 'refund_obligation_id') : null;

                return new AnnualTaxDownstreamResult($id, $type, 'completed', $resource, true);
            }
            $resource = null;
            $links = [];
            if ($type === 'tax_refund_payment') {
                $resource = (int) $this->db->table('payroll_annual_tax_refund_obligations')->insertOrIgnore(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'annual_tax_reconciliation_id' => $id, 'calculation_version' => $this->integer($row, 'calculation_version'), 'amount_minor' => $this->integer($row, 'tax_refund_minor'), 'currency' => $this->string($row, 'currency'), 'status' => 'eligible', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
                $resource = $this->nullableInteger($this->db->table('payroll_annual_tax_refund_obligations')->where('annual_tax_reconciliation_id', $id)->where('calculation_version', $this->integer($row, 'calculation_version'))->first() ?? new stdClass, 'id');
                $links['refund_obligation_id'] = $resource;
            }
            $this->completeIntent($id, $type, $links);

            return new AnnualTaxDownstreamResult($id, $type, 'completed', $resource, false);
        }, 3);
    }

    /** @param array{refund_obligation_id?:int|null,settlement_journal_id?:int} $links */
    private function completeIntent(int $id, string $type, array $links): void
    {
        /** @var array<string,mixed> $values */
        $values = $links + ['status' => 'completed', 'attempt_count' => $this->db->raw('attempt_count + 1'), 'last_attempt_at' => now(), 'completed_at' => now(), 'failure_code' => null, 'failure_message' => null, 'updated_at' => now()];
        $this->db->table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $id)->where('intent_type', $type)->update($values);
    }

    private function validStoredDocument(stdClass $document): bool
    {
        $disk = $this->string($document, 'storage_disk');
        $path = $this->string($document, 'storage_path');
        if ($disk !== self::DISK || $this->string($document, 'mime_type') !== self::MIME || $path === '' || ! $this->files->disk($disk)->exists($path)) {
            return false;
        }
        $bytes = $this->files->disk($disk)->get($path);
        if (! is_string($bytes)) {
            return false;
        }

        return str_starts_with($bytes, '%PDF-') && strlen($bytes) === $this->integer($document, 'file_size') && hash_equals($this->string($document, 'checksum'), hash('sha256', $bytes));
    }

    private function reconciliation(int $companyId, int $id, bool $lock = false): stdClass
    {
        $q = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('id', $id);
        $row = ($lock ? $q->lockForUpdate() : $q)->first();
        if (! $row instanceof stdClass) {
            abort(404);
        }

        return $row;
    }

    private function finalized(stdClass $row): void
    {
        if ($this->string($row, 'status') !== 'finalized' || $this->string($row, 'result_type') === 'blocked') {
            throw ValidationException::withMessages(['reconciliation' => 'Only a non-blocked finalized Annual Tax reconciliation can be delivered.']);
        }
    }

    private function document(stdClass $row, bool $existing): AnnualTaxDocumentData
    {
        return new AnnualTaxDocumentData($this->integer($row, 'id'), $this->integer($row, 'annual_tax_reconciliation_id'), $this->integer($row, 'document_version'), $this->string($row, 'file_name'), $this->string($row, 'mime_type'), $this->integer($row, 'file_size'), $this->string($row, 'checksum'), $existing);
    }

    private function log(stdClass $row, ?int $documentId, int $actorId, string $action, string $result, ?string $ip = null, ?string $agent = null): void
    {
        $this->db->table('payroll_annual_tax_access_logs')->insert(['annual_tax_reconciliation_id' => $this->integer($row, 'id'), 'annual_tax_document_id' => $documentId, 'company_id' => $this->integer($row, 'company_id'), 'employee_id' => $this->integer($row, 'employee_id'), 'actor_id' => $actorId, 'action' => $action, 'result' => $result, 'ip_hash' => $ip === null ? null : hash('sha256', $ip), 'user_agent_summary' => $agent === null ? null : mb_substr($agent, 0, 255), 'accessed_at' => now()]);
    }

    /** @return array<string,mixed> */
    private function jsonObject(stdClass $row, string $key): array
    {
        $raw = $this->string($row, $key);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return [];
        }
        $result = [];
        foreach ($data as $name => $value) {
            if (is_string($name)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private function integer(stdClass $row, string $key): int
    {
        return $this->nullableInteger($row, $key) ?? 0;
    }

    private function nullableInteger(stdClass $row, string $key): ?int
    {
        $v = $row->{$key} ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    private function string(stdClass $row, string $key): string
    {
        $v = $row->{$key} ?? null;

        return is_string($v) ? $v : '';
    }
}
