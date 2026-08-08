<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputEmployeeList;

use Modules\Payroll\Application\DTOs\PayrollInputEmployeeViewModel;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;

final readonly class GetPayrollInputEmployeeListHandler
{
    public function __construct(private PayrollInputOperationsReadRepositoryInterface $repository) {}

    public function handle(GetPayrollInputEmployeeListQuery $query): PayrollInputEmployeeViewModel
    {
        return $this->repository->employeeList($query->companyId, $query->snapshotId, $query->filters);
    }
}
