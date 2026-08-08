<?php

namespace Modules\Payroll\Application\Queries\GetInsurancePolicyList;

final readonly class GetInsurancePolicyListQuery
{
    /** @param array{search:string,status:string,year:string,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
