<?php

namespace Modules\Workflow\Application\Commands\RejectWorkflowTask;

final readonly class RejectWorkflowTaskCommand
{
    public function __construct(public int $companyId, public int $taskId, public int $actorId, public string $key, public string $reason) {}
}
