<?php

namespace Modules\Workflow\Application\Commands\CreateWorkflowDefinition;

use Modules\Workflow\Domain\Services\WorkflowDefinitionService;

final readonly class CreateWorkflowDefinitionHandler
{
    public function __construct(private WorkflowDefinitionService $service) {}

    public function handle(CreateWorkflowDefinitionCommand $command): int
    {
        return $this->service->create($command->data);
    }
}
