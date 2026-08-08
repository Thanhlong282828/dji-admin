<?php

namespace Modules\Payroll\Application\Commands\ClosePayrollPeriod;

use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;

final readonly class ClosePayrollPeriodHandler
{
    public function __construct(private PayrollPeriodOperationsService $service) {}

    public function handle(ClosePayrollPeriodCommand $command): PayrollPeriod
    {
        return $this->service->transition($command->companyId, $command->periodId, PayrollPeriodStatus::Processed, $command->actorId);
    }
}
