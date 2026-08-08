<?php

namespace Modules\Payroll\Application\Commands\ValidatePayrollInputSnapshot;

final readonly class ValidatePayrollInputSnapshotCommand
{
    public function __construct(public int $companyId, public int $snapshotId, public int $actorId, public string $idempotencyKey) {}
}
