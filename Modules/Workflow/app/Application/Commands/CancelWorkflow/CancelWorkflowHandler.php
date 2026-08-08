<?php

namespace Modules\Workflow\Application\Commands\CancelWorkflow;

use Modules\Workflow\Domain\Services\WorkflowExecutionService;

final readonly class CancelWorkflowHandler
{
    public function __construct(private WorkflowExecutionService $service) {}

    public function handle(CancelWorkflowCommand $c): void
    {
        $this->service->cancel($c->companyId, $c->instanceId, $c->actorId, $c->key);
    }
}
