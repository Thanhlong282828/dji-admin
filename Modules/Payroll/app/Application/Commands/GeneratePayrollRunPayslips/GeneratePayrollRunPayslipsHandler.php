<?php

namespace Modules\Payroll\Application\Commands\GeneratePayrollRunPayslips;

use Modules\Payroll\Domain\Services\PayslipGenerationService;

final readonly class GeneratePayrollRunPayslipsHandler
{
    public function __construct(private PayslipGenerationService $service) {}

    public function handle(GeneratePayrollRunPayslipsCommand $c): int
    {
        return $this->service->requestRun($c->companyId, $c->runId, $c->actorId, $c->idempotencyKey);
    }
}
