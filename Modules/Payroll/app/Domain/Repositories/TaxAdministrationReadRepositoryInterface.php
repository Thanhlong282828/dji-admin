<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;

interface TaxAdministrationReadRepositoryInterface
{
    /** @param array{search:string,status:string,method:string,year:string,page:int} $filters */
    public function policies(int $companyId, array $filters): TaxAdministrationViewModel;

    public function policy(int $companyId, int $policyId): TaxAdministrationViewModel;

    /** @param array{search:string,status:string,residency:string,page:int} $filters */
    public function employees(int $companyId, array $filters): TaxAdministrationViewModel;

    public function employee(int $companyId, int $employeeId): TaxAdministrationViewModel;
}
