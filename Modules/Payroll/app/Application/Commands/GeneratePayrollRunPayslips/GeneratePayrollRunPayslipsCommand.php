<?php

namespace Modules\Payroll\Application\Commands\GeneratePayrollRunPayslips;

final readonly class GeneratePayrollRunPayslipsCommand
{
    public function __construct(public int $companyId, public int $runId, public int $actorId, public string $idempotencyKey) {}
}
