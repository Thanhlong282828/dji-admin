<?php

namespace Modules\Workflow\Application\Commands\PublishWorkflowDefinition;

use Modules\Workflow\Domain\Services\WorkflowDefinitionService;

final readonly class PublishWorkflowDefinitionHandler
{
    public function __construct(private WorkflowDefinitionService $service) {}

    public function handle(PublishWorkflowDefinitionCommand $command): int
    {
        return $this->service->publish($command->companyId, $command->definitionId, $command->actorId);
    }
}
