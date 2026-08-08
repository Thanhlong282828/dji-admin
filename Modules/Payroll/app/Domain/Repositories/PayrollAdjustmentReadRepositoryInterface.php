<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\PayrollAdjustmentViewModel;

interface PayrollAdjustmentReadRepositoryInterface
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function listing(int $companyId, array $filters): PayrollAdjustmentViewModel;

    public function createContext(int $companyId): PayrollAdjustmentViewModel;

    public function detail(int $companyId, int $adjustmentId): PayrollAdjustmentViewModel;
}
