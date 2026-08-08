<?php

namespace Modules\Payroll\Application\Queries\GetTaxPolicyList;

final readonly class GetTaxPolicyListQuery
{
    /** @param array{search:string,status:string,method:string,year:string,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
