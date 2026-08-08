<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\PayrollPeriodViewModel;

interface PayrollPeriodReadRepositoryInterface
{
    /** @param array{search?:string,status?:string,year?:int,page?:int} $filters */
    public function listing(int $companyId, array $filters): PayrollPeriodViewModel;

    public function detail(int $companyId, int $periodId): PayrollPeriodViewModel;

    public function openOptions(int $companyId): PayrollPeriodViewModel;
}
