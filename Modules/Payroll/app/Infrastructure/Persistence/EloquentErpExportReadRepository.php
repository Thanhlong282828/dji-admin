<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;

final readonly class EloquentErpExportReadRepository implements ErpExportReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function list(int $companyId, string $search, string $status): ErpExportViewModel
    {
        $q = $this->db->table('erp_exports as e')->join('erp_connections as c', 'c.id', '=', 'e.erp_connection_id')->join('payroll_accounting_journals as j', 'j.id', '=', 'e.accounting_journal_id')->where('e.company_id', $companyId)->select(['e.id', 'e.uuid', 'e.status', 'e.provider', 'e.export_version', 'e.payload_checksum', 'e.external_reference', 'e.created_at', 'j.journal_number', 'c.name as connection_name']);
        if ($search !== '') {
            $q->where(fn (Builder $w): Builder => $w->where('j.journal_number', 'like', '%'.$search.'%')->orWhere('e.uuid', 'like', '%'.$search.'%'));
        }
        if ($status !== '') {
            $q->where('e.status', $status);
        }

        return new ErpExportViewModel(items: array_values($q->orderByDesc('e.id')->limit(100)->get()->map(fn (object $r): array => $this->row($r))->all()));
    }

    public function detail(int $companyId, int $exportId): ErpExportViewModel
    {
        $e = $this->db->table('erp_exports as e')->join('erp_connections as c', 'c.id', '=', 'e.erp_connection_id')->join('payroll_accounting_journals as j', 'j.id', '=', 'e.accounting_journal_id')->where('e.company_id', $companyId)->where('e.id', $exportId)->select(['e.*', 'c.name as connection_name', 'j.journal_number'])->first();
        abort_unless(is_object($e), 404);
        $items = $this->db->table('erp_export_items')->where('company_id', $companyId)->where('erp_export_id', $exportId)->orderBy('line_number')->get()->map(fn (object $r): array => $this->row($r))->all();

        $detail = $this->row($e);
        $detail['items'] = array_values($items);
        $detail['access_logs'] = array_values($this->db->table('erp_access_logs')->where('company_id', $companyId)->where('erp_export_id', $exportId)->orderByDesc('id')->get(['action', 'result', 'actor_id', 'accessed_at'])->map(fn (object $row): array => $this->row($row))->all());

        return new ErpExportViewModel(detail: $detail);
    }

    public function logs(int $companyId, int $exportId): ErpExportViewModel
    {
        $this->detail($companyId, $exportId);

        return new ErpExportViewModel(logs: array_values($this->db->table('erp_export_logs')->where('company_id', $companyId)->where('erp_export_id', $exportId)->orderByDesc('id')->get()->map(fn (object $r): array => $this->row($r))->all()));
    }

    public function createOptions(int $companyId): ErpExportViewModel
    {
        $connections = $this->db->table('erp_connections')->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'provider'])->map(fn (object $r): array => $this->row($r))->all();
        $journals = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->whereIn('status', ['internally_posted', 'exported'])->orderByDesc('id')->get(['id', 'journal_number', 'status', 'currency'])->map(fn (object $r): array => $this->row($r))->all();

        return new ErpExportViewModel(options: ['connections' => array_values($connections), 'journals' => array_values($journals)]);
    }

    public function administration(int $companyId, string $entity, ?int $id = null): ErpExportViewModel
    {
        if ($entity === 'connections') {
            $items = $this->db->table('erp_connections')->where('company_id', $companyId)->orderBy('name')->get(['id', 'uuid', 'name', 'provider', 'endpoint_reference', 'status', 'last_verified_at', 'credential_reference', 'created_at'])->map(function (object $row): array {
                $data = $this->row($row);
                unset($data['credential_reference']);
                $data['has_credential'] = ($row->credential_reference ?? null) !== null ? 1 : 0;

                return $data;
            })->all();

            return new ErpExportViewModel(items: array_values($items));
        }
        if ($entity === 'connection') {
            $row = $this->db->table('erp_connections')->where('company_id', $companyId)->where('id', $id ?? 0)->first();
            abort_unless(is_object($row), 404);
            $detail = $this->row($row);
            unset($detail['credential_reference']);
            $detail['has_credential'] = ($row->credential_reference ?? null) !== null ? 1 : 0;

            return new ErpExportViewModel(detail: $detail);
        }
        if ($entity === 'mappings') {
            $items = $this->db->table('erp_mapping_rules as m')->join('erp_connections as c', 'c.id', '=', 'm.erp_connection_id')->where('m.company_id', $companyId)->select(['m.id', 'm.erp_connection_id', 'm.source_account_code', 'm.external_account_code', 'm.version', 'm.effective_from', 'm.effective_to', 'm.status', 'c.name as connection_name'])->orderBy('c.name')->orderBy('m.source_account_code')->orderByDesc('m.version')->get()->map(fn (object $row): array => $this->row($row))->all();

            return new ErpExportViewModel(items: array_values($items), options: $this->administrationOptions($companyId));
        }
        if ($entity === 'mapping') {
            $row = $this->db->table('erp_mapping_rules as m')->join('erp_connections as c', 'c.id', '=', 'm.erp_connection_id')->where('m.company_id', $companyId)->where('m.id', $id ?? 0)->select(['m.*', 'c.name as connection_name'])->first();
            abort_unless(is_object($row), 404);

            return new ErpExportViewModel(detail: $this->row($row), options: $this->administrationOptions($companyId));
        }
        throw new \InvalidArgumentException('Unsupported ERP administration view.');
    }

    /** @return array<string,list<array<int|string,int|string|null>>> */
    private function administrationOptions(int $companyId): array
    {
        $connections = $this->db->table('erp_connections')->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn (object $row): array => $this->row($row))->all();
        $accounts = $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('status', 'active')->where('allow_posting', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn (object $row): array => $this->row($row))->all();

        return ['connections' => array_values($connections), 'accounts' => array_values($accounts)];
    }

    /** @return array<int|string,int|string|null> */
    private function row(object $r): array
    {
        return array_map(static function (mixed $v): int|string|null {
            if (is_int($v) || is_string($v) || $v === null) {
                return $v;
            }
            if (is_numeric($v)) {
                return (int) $v;
            }

            return is_scalar($v) ? (string) $v : null;
        }, get_object_vars($r));
    }
}
