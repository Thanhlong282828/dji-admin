<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeInsuranceProfileList;

final readonly class GetEmployeeInsuranceProfileListQuery
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
