<?php

namespace Modules\Attendance\Application\Commands\CheckIn;

final readonly class CheckInCommand
{
    public function __construct(public int $companyId, public int $employeeId, public string $source, public string $deduplicationKey, public int $actorId, public ?string $manualReason = null) {}
}
