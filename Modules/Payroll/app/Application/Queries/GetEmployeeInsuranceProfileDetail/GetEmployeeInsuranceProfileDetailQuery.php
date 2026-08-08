<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeInsuranceProfileDetail;

final readonly class GetEmployeeInsuranceProfileDetailQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
