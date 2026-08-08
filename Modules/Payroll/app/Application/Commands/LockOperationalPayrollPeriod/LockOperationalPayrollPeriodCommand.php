<?php

namespace Modules\Payroll\Application\Commands\LockOperationalPayrollPeriod;

final readonly class LockOperationalPayrollPeriodCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $actorId) {}
}
