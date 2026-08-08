<?php

namespace Modules\Payroll\Application\Commands\DeletePayrollPeriod;

final readonly class DeletePayrollPeriodCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $actorId) {}
}
