<?php

namespace Modules\Project\Application\Commands;

final readonly class AddProjectTaskDependencyCommand
{
    public function __construct(public int $companyId, public int $projectId, public int $predecessorId, public int $successorId, public int $actorId) {}
}
