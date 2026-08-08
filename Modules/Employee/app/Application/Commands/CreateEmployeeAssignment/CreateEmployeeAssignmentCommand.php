<?php

namespace Modules\Employee\Application\Commands\CreateEmployeeAssignment;

use Modules\Employee\Application\DTOs\EmployeeAssignmentData;

final readonly class CreateEmployeeAssignmentCommand
{
    public function __construct(public int $companyId, public int $employeeId, public EmployeeAssignmentData $data, public int $actorId) {}
}
