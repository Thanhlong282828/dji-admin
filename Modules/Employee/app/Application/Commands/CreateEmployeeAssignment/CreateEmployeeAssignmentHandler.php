<?php

namespace Modules\Employee\Application\Commands\CreateEmployeeAssignment;

use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Services\EmployeeAssignmentService;

final readonly class CreateEmployeeAssignmentHandler
{
    public function __construct(private EmployeeAssignmentService $service) {}

    public function handle(CreateEmployeeAssignmentCommand $command): EmployeeAssignment
    {
        return $this->service->create($command->companyId, $command->employeeId, $command->data, $command->actorId);
    }
}
