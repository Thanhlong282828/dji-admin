<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollRun;

use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollRunService;

final readonly class CreatePayrollRunHandler
{
    public function __construct(private PayrollRunService $service) {}

    public function handle(CreatePayrollRunCommand $c): PayrollRun
    {
        return $this->service->create($c->companyId, $c->periodId, $c->snapshotId, $c->runNumber, $c->currencyCode, $c->idempotencyKey, $c->actorId);
    }
}
