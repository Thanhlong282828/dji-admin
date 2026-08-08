<?php

namespace Modules\Payroll\Application\Commands\FinalizePayrollRun;

final readonly class FinalizePayrollRunCommand
{
    public function __construct(public int $companyId, public int $runId, public int $expectedVersion, public string $idempotencyKey, public int $actorId) {}
}
