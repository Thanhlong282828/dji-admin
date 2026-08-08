<?php

namespace Modules\Payroll\Application\Queries\GetPayrollPeriodList;

use Modules\Payroll\Application\DTOs\PayrollPeriodViewModel;
use Modules\Payroll\Domain\Repositories\PayrollPeriodReadRepositoryInterface;

final readonly class GetPayrollPeriodListHandler
{
    public function __construct(private PayrollPeriodReadRepositoryInterface $repository) {}

    public function handle(GetPayrollPeriodListQuery $query): PayrollPeriodViewModel
    {
        return $this->repository->listing($query->companyId, $query->filters);
    }
}
