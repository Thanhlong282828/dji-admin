<?php

namespace Modules\Payroll\Application\Commands\CapturePayrollInputSnapshot;

final readonly class CapturePayrollInputSnapshotCommand
{
    public function __construct(public int $companyId, public int $periodId, public int $actorId, public string $idempotencyKey, public ?string $note) {}
}
