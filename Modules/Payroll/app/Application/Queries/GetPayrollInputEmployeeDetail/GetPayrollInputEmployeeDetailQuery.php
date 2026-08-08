<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputEmployeeDetail;

final readonly class GetPayrollInputEmployeeDetailQuery
{
    public function __construct(public int $companyId, public int $snapshotId, public int $employeeInputId) {}
}
