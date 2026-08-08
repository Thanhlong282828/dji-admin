<?php

namespace Modules\Employee\Application\Commands\EndEmployeeAssignment;

use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Services\EmployeeAssignmentService;

final readonly class EndEmployeeAssignmentHandler
{
    public function __construct(private EmployeeAssignmentService $service) {}

    public function handle(EndEmployeeAssignmentCommand $command): EmployeeAssignment
    {
        return $this->service->end($command->companyId, $command->assignmentId, $command->effectiveTo, $command->reason, $command->actorId);
    }
}
