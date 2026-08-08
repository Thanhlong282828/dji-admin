<?php

namespace Modules\Payroll\Application\Queries\GetPayrollRunList;

final readonly class GetPayrollRunListQuery
{
    /** @param array{search:string,status:string,period_id:int,date_from:string,date_to:string,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
