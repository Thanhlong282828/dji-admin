<?php

namespace Modules\Leave\Application\Commands\CreateLeaveRequest;

use Modules\Leave\Domain\Models\LeaveRequest;
use Modules\Leave\Domain\Services\LeaveWorkflowService;

final readonly class CreateLeaveRequestHandler
{
    public function __construct(private LeaveWorkflowService $service) {}

    public function handle(CreateLeaveRequestCommand $command): LeaveRequest
    {
        return $this->service->create($command->companyId, $command->employeeId, $command->policyId, $command->startsAt, $command->endsAt, $command->reason, $command->actorId, $command->idempotencyKey);
    }
}
