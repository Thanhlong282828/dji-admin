<?php

namespace Modules\Workflow\Application\Commands\RejectWorkflowTask;

use Modules\Workflow\Domain\Services\WorkflowTaskService;

final readonly class RejectWorkflowTaskHandler
{
    public function __construct(private WorkflowTaskService $service) {}

    public function handle(RejectWorkflowTaskCommand $c): int
    {
        return $this->service->decide($c->companyId, $c->taskId, $c->actorId, 'rejected', $c->reason, $c->key);
    }
}
