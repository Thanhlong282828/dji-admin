<?php

namespace Modules\Payroll\Application\Commands\CalculatePayrollRun;

final readonly class CalculatePayrollRunCommand
{
    public function __construct(public int $companyId, public int $runId, public int $expectedVersion, public string $idempotencyKey, public int $actorId) {}
}
