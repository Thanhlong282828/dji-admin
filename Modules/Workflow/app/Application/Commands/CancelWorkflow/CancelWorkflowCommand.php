<?php

namespace Modules\Workflow\Application\Commands\CancelWorkflow;

final readonly class CancelWorkflowCommand
{
    public function __construct(public int $companyId, public int $instanceId, public int $actorId, public string $key) {}
}
