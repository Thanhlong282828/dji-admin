<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class CompleteWorkflowAccessRequestHandler
{
    public function __construct(private IamGovernanceService $service) {}

    public function handle(CompleteWorkflowAccessRequestCommand $command): void
    {
        $this->service->completeWorkflow($command->companyId, $command->requestId, $command->workflowInstanceId, $command->actorId);
    }
}
