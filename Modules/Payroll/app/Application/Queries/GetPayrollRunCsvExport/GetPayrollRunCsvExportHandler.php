<?php

namespace Modules\Payroll\Application\Queries\GetPayrollRunCsvExport;

use App\Support\StreamingCsvResponseFactory;
use Modules\Payroll\Domain\Repositories\PayrollExportReadRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GetPayrollRunCsvExportHandler
{
    public function __construct(private PayrollExportReadRepositoryInterface $repository, private StreamingCsvResponseFactory $csv) {}

    public function handle(GetPayrollRunCsvExportQuery $query): StreamedResponse
    {
        return $this->csv->make('payroll-run-'.$query->runId.'-'.now()->format('Ymd-His').'.csv', ['Run Number', 'Period Start', 'Period End', 'Input Snapshot Version', 'Employee Number', 'Employee Name', 'Organization Unit', 'Job Position', 'Currency', 'Scheduled Minutes', 'Worked Minutes', 'Paid Leave Minutes', 'Unpaid Leave Minutes', 'Approved Overtime Minutes', 'Gross Amount', 'Employee Deduction Amount', 'Employer Contribution Amount', 'Net Amount', 'Calculation Status', 'Validation Status', 'Calculation Version', 'Calculated At'], $this->repository->payrollItems($query->companyId, $query->runId, $query->filters));
    }
}
