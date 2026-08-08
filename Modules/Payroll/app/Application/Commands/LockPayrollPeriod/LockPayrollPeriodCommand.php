<?php

namespace Modules\Payroll\Application\Commands\LockPayrollPeriod;

final readonly class LockPayrollPeriodCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $expectedVersion, public string $reason, public int $actorId) {}
}
