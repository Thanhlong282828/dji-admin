<?php

namespace Modules\Payroll\Application\Commands\DeletePayrollPeriod;

use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;

final readonly class DeletePayrollPeriodHandler
{
    public function __construct(private PayrollPeriodOperationsService $service) {}

    public function handle(DeletePayrollPeriodCommand $command): void
    {
        $this->service->delete($command->companyId, $command->periodId, $command->actorId);
    }
}
