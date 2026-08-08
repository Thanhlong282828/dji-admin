<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputEmployeeDetail;

use Modules\Payroll\Application\DTOs\PayrollInputEmployeeViewModel;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;

final readonly class GetPayrollInputEmployeeDetailHandler
{
    public function __construct(private PayrollInputOperationsReadRepositoryInterface $repository) {}

    public function handle(GetPayrollInputEmployeeDetailQuery $query): PayrollInputEmployeeViewModel
    {
        return $this->repository->employeeDetail($query->companyId, $query->snapshotId, $query->employeeInputId);
    }
}
