<?php

namespace Modules\Payroll\Application\Queries\GetPayrollPeriodDetail;

final readonly class GetPayrollPeriodDetailQuery
{
    public function __construct(public int $companyId, public int $periodId) {}
}
