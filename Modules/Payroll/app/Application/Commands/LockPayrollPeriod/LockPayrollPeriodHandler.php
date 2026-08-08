<?php

namespace Modules\Payroll\Application\Commands\LockPayrollPeriod;

use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Services\PayrollInputGenerationService;

final readonly class LockPayrollPeriodHandler
{
    public function __construct(private PayrollInputGenerationService $service) {}

    public function handle(LockPayrollPeriodCommand $command): PayrollPeriod
    {
        return $this->service->lock($command->companyId, $command->periodId, $command->expectedVersion, $command->reason, $command->actorId);
    }
}
