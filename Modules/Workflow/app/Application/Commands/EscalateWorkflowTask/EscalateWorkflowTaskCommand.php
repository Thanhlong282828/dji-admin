<?php

namespace Modules\Workflow\Application\Commands\EscalateWorkflowTask;

final readonly class EscalateWorkflowTaskCommand
{
    public function __construct(public int $companyId, public int $taskId, public int $actorId, public string $key) {}
}
