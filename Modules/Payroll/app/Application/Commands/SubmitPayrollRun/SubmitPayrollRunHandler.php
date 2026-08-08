<?php

namespace Modules\Payroll\Application\Commands\SubmitPayrollRun;

use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollRunService;

final readonly class SubmitPayrollRunHandler
{
    public function __construct(private PayrollRunService $service) {}

    public function handle(SubmitPayrollRunCommand $c): PayrollRun
    {
        return $this->service->transition($c->companyId, $c->runId, PayrollRunStatus::Submitted, $c->expectedVersion, $c->idempotencyKey, $c->actorId);
    }
}
