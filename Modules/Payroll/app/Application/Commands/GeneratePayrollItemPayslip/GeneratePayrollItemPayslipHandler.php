<?php

namespace Modules\Payroll\Application\Commands\GeneratePayrollItemPayslip;

use Modules\Payroll\Domain\Services\PayslipGenerationService;

final readonly class GeneratePayrollItemPayslipHandler
{
    public function __construct(private PayslipGenerationService $service) {}

    public function handle(GeneratePayrollItemPayslipCommand $c): int
    {
        return $this->service->requestItem($c->companyId, $c->runId, $c->itemId, $c->actorId, $c->idempotencyKey);
    }
}
