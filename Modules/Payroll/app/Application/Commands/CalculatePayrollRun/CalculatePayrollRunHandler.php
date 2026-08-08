<?php

namespace Modules\Payroll\Application\Commands\CalculatePayrollRun;

use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollRunService;

final readonly class CalculatePayrollRunHandler
{
    public function __construct(private PayrollRunService $service) {}

    public function handle(CalculatePayrollRunCommand $c): PayrollRun
    {
        return $this->service->calculate($c->companyId, $c->runId, $c->expectedVersion, $c->idempotencyKey, $c->actorId);
    }
}
