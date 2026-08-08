<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\PayslipViewModel;

interface PayslipReadRepositoryInterface
{
    /** @param array{search:string,status:string,run:int,page:int} $filters */
    public function list(int $company, array $filters): PayslipViewModel;

    public function detail(int $company, int $id): PayslipViewModel;
}
