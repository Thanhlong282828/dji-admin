<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\PayrollInputEmployeeViewModel;
use Modules\Payroll\Application\DTOs\PayrollInputOperationsViewModel;

interface PayrollInputOperationsReadRepositoryInterface
{
    /** @param array<string, int|string|null> $filters */
    public function listing(int $companyId, array $filters): PayrollInputOperationsViewModel;

    public function createOptions(int $companyId): PayrollInputOperationsViewModel;

    public function detail(int $companyId, int $snapshotId): PayrollInputOperationsViewModel;

    /** @param array{search:string,status:string,page:int} $filters */
    public function employeeList(int $companyId, int $snapshotId, array $filters): PayrollInputEmployeeViewModel;

    public function employeeDetail(int $companyId, int $snapshotId, int $employeeInputId): PayrollInputEmployeeViewModel;
}
