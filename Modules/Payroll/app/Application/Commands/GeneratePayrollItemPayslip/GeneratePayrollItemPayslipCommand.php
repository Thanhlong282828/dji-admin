<?php

namespace Modules\Payroll\Application\Commands\GeneratePayrollItemPayslip;

final readonly class GeneratePayrollItemPayslipCommand
{
    public function __construct(public int $companyId, public int $runId, public int $itemId, public int $actorId, public string $idempotencyKey) {}
}
