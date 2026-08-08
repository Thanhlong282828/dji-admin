<?php

namespace Modules\Employee\Application\Commands\EndEmployeeAssignment;

use Carbon\CarbonImmutable;

final readonly class EndEmployeeAssignmentCommand
{
    public function __construct(public int $companyId, public int $assignmentId, public CarbonImmutable $effectiveTo, public string $reason, public int $actorId) {}
}
