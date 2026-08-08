<?php

namespace Modules\Payroll\Application\Queries\GetPayrollComponentBreakdownCsvExport;

use App\Support\StreamingCsvResponseFactory;
use Modules\Payroll\Domain\Repositories\PayrollExportReadRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GetPayrollComponentBreakdownCsvExportHandler
{
    public function __construct(private PayrollExportReadRepositoryInterface $repository, private StreamingCsvResponseFactory $csv) {}

    public function handle(GetPayrollComponentBreakdownCsvExportQuery $query): StreamedResponse
    {
        return $this->csv->make('payroll-components-'.$query->runId.'-'.now()->format('Ymd-His').'.csv', ['Run Number', 'Employee Number', 'Employee Name', 'Component Code', 'Component Name', 'Category', 'Calculation Type', 'Source Type', 'Quantity Basis Points', 'Rate Minor', 'Percentage Basis Points', 'Base Amount Minor', 'Amount Minor', 'Currency', 'Calculation Order', 'Calculation Version'], $this->repository->components($query->companyId, $query->runId, $query->filters));
    }
}
