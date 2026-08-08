<?php

namespace Modules\Payroll\Application\Queries\GetPayrollPeriodList;

final readonly class GetPayrollPeriodListQuery
{
    /** @param array{search?:string,status?:string,year?:int,page?:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
