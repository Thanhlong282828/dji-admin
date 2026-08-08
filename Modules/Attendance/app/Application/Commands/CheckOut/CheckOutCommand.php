<?php

namespace Modules\Attendance\Application\Commands\CheckOut;

final readonly class CheckOutCommand
{
    public function __construct(public int $companyId, public int $employeeId, public string $source, public string $deduplicationKey, public int $actorId, public ?string $manualReason = null) {}
}
