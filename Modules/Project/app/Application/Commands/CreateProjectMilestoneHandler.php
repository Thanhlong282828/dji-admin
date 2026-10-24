<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class CreateProjectMilestoneHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(CreateProjectMilestoneCommand $command): int
    {
        return $this->service->createMilestone($command->companyId, $command->projectId, $command->data, $command->actorId);
    }
}
