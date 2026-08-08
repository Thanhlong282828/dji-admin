<?php

namespace Modules\Payroll\Application\Commands\ClosePayrollPeriod;

final readonly class ClosePayrollPeriodCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $actorId) {}
}
