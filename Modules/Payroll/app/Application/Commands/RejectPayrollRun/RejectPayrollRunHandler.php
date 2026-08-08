<?php

namespace Modules\Payroll\Application\Commands\RejectPayrollRun;

use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollRunService;

final readonly class RejectPayrollRunHandler
{
    public function __construct(private PayrollRunService $service) {}

    public function handle(RejectPayrollRunCommand $c): PayrollRun
    {
        return $this->service->transition($c->companyId, $c->runId, PayrollRunStatus::Rejected, $c->expectedVersion, $c->idempotencyKey, $c->actorId, $c->reason);
    }
}
