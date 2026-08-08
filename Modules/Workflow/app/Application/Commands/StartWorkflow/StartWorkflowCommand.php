<?php

namespace Modules\Workflow\Application\Commands\StartWorkflow;

use Modules\Workflow\Application\DTOs\StartWorkflowData;

final readonly class StartWorkflowCommand
{
    public function __construct(public StartWorkflowData $data) {}
}
