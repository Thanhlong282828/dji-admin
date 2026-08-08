<?php

namespace Modules\Payroll\Application\Commands\OpenPayrollPeriod;

final readonly class OpenPayrollPeriodCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $actorId) {}
}
