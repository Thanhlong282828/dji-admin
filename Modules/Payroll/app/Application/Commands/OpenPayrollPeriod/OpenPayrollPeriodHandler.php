<?php

namespace Modules\Payroll\Application\Commands\OpenPayrollPeriod;

use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;

final readonly class OpenPayrollPeriodHandler
{
    public function __construct(private PayrollPeriodOperationsService $service) {}

    public function handle(OpenPayrollPeriodCommand $command): PayrollPeriod
    {
        return $this->service->transition($command->companyId, $command->periodId, PayrollPeriodStatus::Collecting, $command->actorId);
    }
}
