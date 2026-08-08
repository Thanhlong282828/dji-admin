<?php

namespace Modules\Workflow\Application\Commands\CreateWorkflowDefinition;

use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;

final readonly class CreateWorkflowDefinitionCommand
{
    public function __construct(public WorkflowDefinitionData $data) {}
}
