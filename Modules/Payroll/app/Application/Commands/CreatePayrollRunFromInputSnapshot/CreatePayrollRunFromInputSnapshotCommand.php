<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollRunFromInputSnapshot;

final readonly class CreatePayrollRunFromInputSnapshotCommand
{
    public function __construct(public int $companyId, public int $snapshotId, public int $actorId, public string $idempotencyKey) {}
}
