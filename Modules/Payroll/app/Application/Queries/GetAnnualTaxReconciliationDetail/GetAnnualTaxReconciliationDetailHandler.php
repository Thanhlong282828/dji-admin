<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxReconciliationDetail;

use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Application\DTOs\AnnualTaxReconciliationDetailViewModel;
use Modules\Payroll\Application\Queries\GetAnnualTaxHistory\GetAnnualTaxHistoryHandler;
use Modules\Payroll\Application\Queries\GetAnnualTaxHistory\GetAnnualTaxHistoryQuery;
use Modules\Payroll\Application\Queries\GetAnnualTaxSource\GetAnnualTaxSourceHandler;
use Modules\Payroll\Application\Queries\GetAnnualTaxSource\GetAnnualTaxSourceQuery;

final readonly class GetAnnualTaxReconciliationDetailHandler
{
    public function __construct(private ConnectionInterface $db, private GetAnnualTaxSourceHandler $sources, private GetAnnualTaxHistoryHandler $history) {}

    public function handle(GetAnnualTaxReconciliationDetailQuery $query): AnnualTaxReconciliationDetailViewModel
    {
        $row = $this->db->table('payroll_annual_tax_reconciliations as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.company_id', $query->companyId)->where('a.id', $query->reconciliationId)->select(['a.*', 'e.employee_code', 'e.full_name'])->first();
        if (! $row instanceof \stdClass) {
            abort(404);
        } $sources = $this->sources->handle(new GetAnnualTaxSourceQuery($query->companyId, $query->reconciliationId, $query->sourcesPage, $query->componentsPage, $query->issuesPage));
        $history = $this->history->handle(new GetAnnualTaxHistoryQuery($query->companyId, $query->reconciliationId, $query->historyPage));
        $intents = [];
        foreach ($this->db->table('payroll_annual_tax_downstream_intents')->where('company_id', $query->companyId)->where('annual_tax_reconciliation_id', $query->reconciliationId)->orderBy('intent_type')->limit(10)->get() as $item) {
            $intents[] = $this->row($item);
        }
        $documents = [];
        foreach ($this->db->table('payroll_annual_tax_documents')->where('company_id', $query->companyId)->where('annual_tax_reconciliation_id', $query->reconciliationId)->orderByDesc('document_version')->limit(10)->get(['id', 'document_type', 'document_version', 'is_current', 'file_name', 'mime_type', 'file_size', 'checksum', 'generated_at']) as $document) {
            $documents[] = $this->row($document);
        }

        return new AnnualTaxReconciliationDetailViewModel($this->row($row), $sources, $history, $intents, $documents);
    }

    /** @return array<string,mixed> */
    private function row(object $row): array
    {
        $result = [];
        foreach ((array) $row as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
