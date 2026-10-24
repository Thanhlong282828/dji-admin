<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class ChangeProjectMilestoneStatusHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(ChangeProjectMilestoneStatusCommand $command): void
    {
        $this->service->changeMilestoneStatus($command->companyId, $command->projectId, $command->milestoneId, $command->status, $command->actorId);
    }
}
