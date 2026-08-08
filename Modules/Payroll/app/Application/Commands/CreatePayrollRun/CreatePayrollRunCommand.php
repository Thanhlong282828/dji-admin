<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollRun;

final readonly class CreatePayrollRunCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $snapshotId, public string $runNumber, public string $currencyCode, public string $idempotencyKey, public int $actorId) {}
}
