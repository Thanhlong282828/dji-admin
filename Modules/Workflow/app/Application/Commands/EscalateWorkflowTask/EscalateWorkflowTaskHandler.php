<?php

namespace Modules\Workflow\Application\Commands\EscalateWorkflowTask;

use Modules\Workflow\Domain\Services\WorkflowTaskService;

final readonly class EscalateWorkflowTaskHandler
{
    public function __construct(private WorkflowTaskService $service) {}

    public function handle(EscalateWorkflowTaskCommand $c): int
    {
        return $this->service->escalate($c->companyId, $c->taskId, $c->actorId, $c->key);
    }
}
