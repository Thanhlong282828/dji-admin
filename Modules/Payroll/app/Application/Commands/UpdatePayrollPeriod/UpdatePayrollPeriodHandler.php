<?php

namespace Modules\Payroll\Application\Commands\UpdatePayrollPeriod;

use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;

final readonly class UpdatePayrollPeriodHandler
{
    public function __construct(private PayrollPeriodOperationsService $service) {}

    public function handle(UpdatePayrollPeriodCommand $command): PayrollPeriod
    {
        return $this->service->update($command->companyId, $command->periodId, $command->data, $command->actorId);
    }
}
