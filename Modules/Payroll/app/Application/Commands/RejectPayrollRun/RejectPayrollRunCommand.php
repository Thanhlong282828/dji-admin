<?php

namespace Modules\Payroll\Application\Commands\RejectPayrollRun;

final readonly class RejectPayrollRunCommand
{
    public function __construct(public int $companyId, public int $runId, public int $expectedVersion, public string $idempotencyKey, public int $actorId, public string $reason) {}
}
