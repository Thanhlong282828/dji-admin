<?php

namespace Modules\Project\Application\Commands;

final readonly class ChangeProjectMilestoneStatusCommand
{
    public function __construct(public int $companyId, public int $projectId, public int $milestoneId, public string $status, public int $actorId) {}
}
