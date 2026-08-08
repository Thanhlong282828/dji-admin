<?php

namespace Modules\Payroll\Application\Commands\LockOperationalPayrollPeriod;

use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;

final readonly class LockOperationalPayrollPeriodHandler
{
    public function __construct(private PayrollPeriodOperationsService $service) {}

    public function handle(LockOperationalPayrollPeriodCommand $command): PayrollPeriod
    {
        return $this->service->transition($command->companyId, $command->periodId, PayrollPeriodStatus::Locked, $command->actorId);
    }
}
