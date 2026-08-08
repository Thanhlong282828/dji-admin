<?php

namespace Modules\Workflow\Application\Commands\PublishWorkflowDefinition;

final readonly class PublishWorkflowDefinitionCommand
{
    public function __construct(public int $companyId, public int $definitionId, public int $actorId) {}
}
