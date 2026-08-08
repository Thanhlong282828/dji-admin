<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeTaxProfileDetail;

final readonly class GetEmployeeTaxProfileDetailQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
