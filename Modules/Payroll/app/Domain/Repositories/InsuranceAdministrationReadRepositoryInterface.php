<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;

interface InsuranceAdministrationReadRepositoryInterface
{
    /** @param array{search:string,status:string,year:string,page:int} $filters */
    public function policies(int $companyId, array $filters): InsuranceAdministrationViewModel;

    public function policy(int $companyId, int $policyId): InsuranceAdministrationViewModel;

    /** @param array{search:string,status:string,page:int} $filters */
    public function employees(int $companyId, array $filters): InsuranceAdministrationViewModel;

    public function employee(int $companyId, int $employeeId): InsuranceAdministrationViewModel;
}
