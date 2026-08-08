<?php

namespace Modules\Workflow\Application\Commands\ClaimWorkflowTask;

final readonly class ClaimWorkflowTaskCommand
{
    public function __construct(public int $companyId, public int $taskId, public int $actorId, public int $version, public string $key) {}
}
