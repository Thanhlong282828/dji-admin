<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeTaxProfileList;

final readonly class GetEmployeeTaxProfileListQuery
{
    /** @param array{search:string,status:string,residency:string,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
