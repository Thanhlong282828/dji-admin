<?php

namespace Modules\Payroll\Application\Commands\SubmitPayrollRun;

final readonly class SubmitPayrollRunCommand
{
    public function __construct(public int $companyId, public int $runId, public int $expectedVersion, public string $idempotencyKey, public int $actorId) {}
}
