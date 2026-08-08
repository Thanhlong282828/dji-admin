<?php

namespace Modules\Workflow\Application\Commands\ApproveWorkflowTask;

use Modules\Workflow\Domain\Services\WorkflowTaskService;

final readonly class ApproveWorkflowTaskHandler
{
    public function __construct(private WorkflowTaskService $service) {}

    public function handle(ApproveWorkflowTaskCommand $c): int
    {
        return $this->service->decide($c->companyId, $c->taskId, $c->actorId, 'approved', $c->reason, $c->key);
    }
}
