<?php

namespace Modules\Payroll\Application\Commands\ApprovePayrollRun;

final readonly class ApprovePayrollRunCommand
{
    public function __construct(public int $companyId, public int $runId, public int $expectedVersion, public string $idempotencyKey, public int $actorId) {}
}
