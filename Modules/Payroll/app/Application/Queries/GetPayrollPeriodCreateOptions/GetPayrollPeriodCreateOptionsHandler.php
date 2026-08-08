<?php

namespace Modules\Payroll\Application\Queries\GetPayrollPeriodCreateOptions;

use Modules\Payroll\Application\DTOs\PayrollPeriodViewModel;
use Modules\Payroll\Domain\Repositories\PayrollPeriodReadRepositoryInterface;

final readonly class GetPayrollPeriodCreateOptionsHandler
{
    public function __construct(private PayrollPeriodReadRepositoryInterface $repository) {}

    public function handle(GetPayrollPeriodCreateOptionsQuery $query): PayrollPeriodViewModel
    {
        return $this->repository->openOptions($query->companyId);
    }
}
