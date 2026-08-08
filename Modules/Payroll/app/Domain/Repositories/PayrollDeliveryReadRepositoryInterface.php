<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\PayrollDeliveryViewModel;
use Modules\Payroll\Application\DTOs\PayrollItemDetailViewModel;
use Modules\Payroll\Application\DTOs\PayrollRunDetailViewModel;
use Modules\Payroll\Application\DTOs\PayrollRunListViewModel;

interface PayrollDeliveryReadRepositoryInterface
{
    public function dashboard(int $company): PayrollDeliveryViewModel;

    public function runDetail(int $company, int $run): PayrollRunDetailViewModel;

    /** @param array{search:string,status:string,period_id:int,date_from:string,date_to:string,page:int} $filters */
    public function runList(int $company, array $filters): PayrollRunListViewModel;

    public function itemDetail(int $company, int $run, int $item): PayrollItemDetailViewModel;
}
