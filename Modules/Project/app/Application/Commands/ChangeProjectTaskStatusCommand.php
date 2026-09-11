<?php

namespace Modules\Project\Application\Commands;

final readonly class ChangeProjectTaskStatusCommand
{
    public function __construct(public int $companyId, public int $projectId, public int $taskId, public string $status, public int $actorId) {}
}
