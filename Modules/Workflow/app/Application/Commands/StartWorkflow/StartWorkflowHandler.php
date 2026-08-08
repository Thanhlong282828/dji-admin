<?php

namespace Modules\Workflow\Application\Commands\StartWorkflow;

use Modules\Workflow\Domain\Services\WorkflowExecutionService;

final readonly class StartWorkflowHandler
{
    public function __construct(private WorkflowExecutionService $service) {}

    public function handle(StartWorkflowCommand $command): int
    {
        return $this->service->start($command->data);
    }
}
