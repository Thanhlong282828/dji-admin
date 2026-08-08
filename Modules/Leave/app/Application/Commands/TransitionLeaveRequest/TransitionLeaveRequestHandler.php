<?php

namespace Modules\Leave\Application\Commands\TransitionLeaveRequest;

use Modules\Leave\Domain\Models\LeaveRequest;
use Modules\Leave\Domain\Services\LeaveWorkflowService;

final readonly class TransitionLeaveRequestHandler
{
    public function __construct(private LeaveWorkflowService $service) {}

    public function handle(TransitionLeaveRequestCommand $command): LeaveRequest
    {
        return $this->service->transition($command->companyId, $command->requestId, $command->target, $command->expectedVersion, $command->actorId, $command->comment);
    }
}
