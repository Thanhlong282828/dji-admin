<?php

namespace Modules\Employee\Application\Commands\TransferEmployee;

use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Services\EmployeeAssignmentService;

final readonly class TransferEmployeeHandler
{
    public function __construct(private EmployeeAssignmentService $service) {}

    public function handle(TransferEmployeeCommand $command): EmployeeAssignment
    {
        return $this->service->transfer($command->companyId, $command->employeeId, $command->currentAssignmentId, $command->data, $command->reason, $command->actorId, $command->promotion);
    }
}
