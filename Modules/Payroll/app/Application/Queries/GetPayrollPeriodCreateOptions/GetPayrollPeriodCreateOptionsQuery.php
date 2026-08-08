<?php

namespace Modules\Payroll\Application\Queries\GetPayrollPeriodCreateOptions;

final readonly class GetPayrollPeriodCreateOptionsQuery
{
    public function __construct(public int $companyId) {}
}
