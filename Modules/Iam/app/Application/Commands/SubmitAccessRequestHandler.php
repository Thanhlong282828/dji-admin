<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;

final readonly class SubmitAccessRequestHandler
{
    public function __construct(private IamGovernanceService $s, private WorkflowExecutionService $workflow) {}

    public function handle(SubmitAccessRequestCommand $c): void
    {
        if ($c->workflowDefinitionId === null) {
            $this->s->transition($c->companyId, $c->requestId, $c->actorId, 'submit');

            return;
        }
        $instanceId = $this->workflow->start(new StartWorkflowData($c->companyId, $c->actorId, $c->workflowDefinitionId, 'iam_access_request', $c->requestId, 'iam-request-'.$c->requestId, ['request_id' => $c->requestId]));
        $this->s->attachWorkflow($c->companyId, $c->requestId, $c->actorId, $instanceId);
    }
}
