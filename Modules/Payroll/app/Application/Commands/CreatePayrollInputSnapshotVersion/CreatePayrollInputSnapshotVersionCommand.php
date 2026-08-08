<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollInputSnapshotVersion;

final readonly class CreatePayrollInputSnapshotVersionCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $actorId, public string $idempotencyKey, public ?string $note) {}
}
