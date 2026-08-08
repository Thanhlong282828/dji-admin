<?php

namespace Modules\Employee\Application\Commands\UpdateEmployeeAssignment;

use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Services\EmployeeAssignmentService;

final readonly class UpdateEmployeeAssignmentHandler
{
    public function __construct(private EmployeeAssignmentService $service) {}

    public function handle(UpdateEmployeeAssignmentCommand $command): EmployeeAssignment
    {
        return $this->service->update($command->companyId, $command->assignmentId, $command->data, $command->actorId);
    }
}
