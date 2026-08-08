<?php

namespace Modules\Workflow\Application\Commands\ClaimWorkflowTask;

use Modules\Workflow\Domain\Services\WorkflowTaskService;

final readonly class ClaimWorkflowTaskHandler
{
    public function __construct(private WorkflowTaskService $service) {}

    public function handle(ClaimWorkflowTaskCommand $c): int
    {
        return $this->service->claim($c->companyId, $c->taskId, $c->actorId, $c->version, $c->key);
    }
}
