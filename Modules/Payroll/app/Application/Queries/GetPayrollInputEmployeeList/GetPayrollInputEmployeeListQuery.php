<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputEmployeeList;

final readonly class GetPayrollInputEmployeeListQuery
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function __construct(public int $companyId, public int $snapshotId, public array $filters) {}
}
