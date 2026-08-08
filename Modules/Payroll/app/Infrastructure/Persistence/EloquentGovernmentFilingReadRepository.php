<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;
use Modules\Payroll\Domain\Repositories\GovernmentFilingReadRepositoryInterface;

final readonly class EloquentGovernmentFilingReadRepository implements GovernmentFilingReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function list(int $companyId, int $taxYear, string $status): GovernmentFilingViewModel
    {
        $query = $this->db->table('government_tax_filings as f')->join('payroll_annual_tax_reconciliations as a', 'a.id', '=', 'f.annual_tax_reconciliation_id')->where('f.company_id', $companyId)->select(['f.id', 'f.uuid', 'f.tax_year', 'f.jurisdiction', 'f.filing_type', 'f.version', 'f.status', 'f.source_checksum', 'f.payload_checksum', 'f.created_at', 'a.reference as annual_reference']);
        if ($taxYear > 0) {
            $query->where('f.tax_year', $taxYear);
        }
        if ($status !== '') {
            $query->where('f.status', $status);
        }

        return new GovernmentFilingViewModel(items: array_values($query->orderByDesc('f.id')->limit(100)->get()->map(fn (object $row): array => $this->row($row))->all()));
    }

    public function detail(int $companyId, int $filingId): GovernmentFilingViewModel
    {
        $row = $this->db->table('government_tax_filings as f')->join('payroll_annual_tax_reconciliations as a', 'a.id', '=', 'f.annual_tax_reconciliation_id')->where('f.company_id', $companyId)->where('f.id', $filingId)->select(['f.*', 'a.reference as annual_reference', 'a.result_type', 'a.currency'])->first();
        abort_unless(is_object($row), 404);
        $detail = $this->row($row);
        $detail['items'] = $this->rows($this->db->table('government_tax_filing_items')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderBy('line_number')->get()->all());
        $detail['documents'] = $this->rows($this->db->table('government_tax_filing_documents')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderByDesc('document_version')->get(['id', 'uuid', 'document_version', 'file_name', 'mime_type', 'file_size', 'checksum', 'generated_at'])->all());
        $detail['issues'] = $this->rows($this->db->table('government_tax_validation_issues')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderByDesc('id')->get()->all());
        $detail['attempts'] = $this->rows($this->db->table('government_tax_submission_attempts')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderByDesc('attempt_number')->get(['attempt_number', 'payload_hash', 'request_hash', 'response_hash', 'status', 'error_code', 'submitted_by', 'submitted_at'])->all());
        $detail['access_logs'] = $this->rows($this->db->table('government_tax_access_logs')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderByDesc('id')->get(['action', 'result', 'actor_id', 'accessed_at'])->all());

        return new GovernmentFilingViewModel(detail: $detail);
    }

    public function history(int $companyId, int $filingId): GovernmentFilingViewModel
    {
        $this->detail($companyId, $filingId);

        return new GovernmentFilingViewModel(history: $this->rows($this->db->table('government_tax_filing_logs')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderByDesc('id')->get()->all()));
    }

    public function createOptions(int $companyId): GovernmentFilingViewModel
    {
        $items = $this->rows($this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('status', 'finalized')->whereNotNull('source_checksum')->orderByDesc('tax_year')->get(['id', 'reference', 'tax_year', 'jurisdiction', 'result_type', 'source_checksum'])->all());

        return new GovernmentFilingViewModel(items: $items);
    }

    /**
     * @param  array<int, object>  $rows
     * @return list<array<string, int|string|null>>
     */
    private function rows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->row($row);
        }

        return $result;
    }

    /** @return array<string,int|string|null> */
    private function row(object $row): array
    {
        $result = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key)) {
                $result[$key] = is_int($value) || is_string($value) || $value === null ? $value : (is_numeric($value) ? (int) $value : null);
            }
        }

        return $result;
    }
}
