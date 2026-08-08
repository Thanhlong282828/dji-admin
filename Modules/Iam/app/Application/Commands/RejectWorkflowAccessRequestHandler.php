<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class RejectWorkflowAccessRequestHandler
{
    public function __construct(private IamGovernanceService $service) {}

    public function handle(RejectWorkflowAccessRequestCommand $command): void
    {
        $this->service->rejectWorkflow($command->companyId, $command->requestId, $command->workflowInstanceId, $command->actorId);
    }
}
