<?php

namespace Modules\Workflow\Application\Commands\ApproveWorkflowTask;

final readonly class ApproveWorkflowTaskCommand
{
    public function __construct(public int $companyId, public int $taskId, public int $actorId, public string $key, public ?string $reason = null) {}
}
