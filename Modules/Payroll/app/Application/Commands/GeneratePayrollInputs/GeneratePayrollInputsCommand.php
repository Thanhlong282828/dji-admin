<?php

namespace Modules\Payroll\Application\Commands\GeneratePayrollInputs;

final readonly class GeneratePayrollInputsCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $expectedVersion, public int $actorId) {}
}
