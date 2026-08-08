<?php

namespace Modules\Employee\Application\Commands\UpdateEmployeeAssignment;

use Modules\Employee\Application\DTOs\EmployeeAssignmentData;

final readonly class UpdateEmployeeAssignmentCommand
{
    public function __construct(public int $companyId, public int $assignmentId, public EmployeeAssignmentData $data, public int $actorId) {}
}
