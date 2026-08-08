<?php

namespace Modules\Payroll\Application\Commands\FinalizePayrollRun;

use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollRunService;

final readonly class FinalizePayrollRunHandler
{
    public function __construct(private PayrollRunService $service) {}

    public function handle(FinalizePayrollRunCommand $c): PayrollRun
    {
        return $this->service->transition($c->companyId, $c->runId, PayrollRunStatus::Finalized, $c->expectedVersion, $c->idempotencyKey, $c->actorId);
    }
}
