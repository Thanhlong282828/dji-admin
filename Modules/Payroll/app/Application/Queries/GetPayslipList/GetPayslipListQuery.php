<?php

namespace Modules\Payroll\Application\Queries\GetPayslipList;

final readonly class GetPayslipListQuery
{
    /** @param array{search:string,status:string,run:int,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
