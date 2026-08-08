<?php

namespace Modules\Payroll\Application\Commands\GeneratePayrollInputs;

use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Services\PayrollInputGenerationService;

final readonly class GeneratePayrollInputsHandler
{
    public function __construct(private PayrollInputGenerationService $service) {}

    public function handle(GeneratePayrollInputsCommand $command): PayrollInputSnapshot
    {
        return $this->service->generate($command->companyId, $command->periodId, $command->expectedVersion, $command->actorId);
    }
}
