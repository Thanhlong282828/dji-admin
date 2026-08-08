<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollPeriod;

use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;

final readonly class CreatePayrollPeriodHandler
{
    public function __construct(private PayrollPeriodOperationsService $service) {}

    public function handle(CreatePayrollPeriodCommand $command): PayrollPeriod
    {
        return $this->service->create($command->companyId, $command->data, $command->actorId, $command->idempotencyKey);
    }
}
