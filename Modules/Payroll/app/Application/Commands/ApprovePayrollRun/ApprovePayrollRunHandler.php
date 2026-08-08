<?php

namespace Modules\Payroll\Application\Commands\ApprovePayrollRun;

use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollRunService;

final readonly class ApprovePayrollRunHandler
{
    public function __construct(private PayrollRunService $service) {}

    public function handle(ApprovePayrollRunCommand $c): PayrollRun
    {
        return $this->service->transition($c->companyId, $c->runId, PayrollRunStatus::Approved, $c->expectedVersion, $c->idempotencyKey, $c->actorId);
    }
}
