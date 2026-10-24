<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class AddProjectTaskDependencyHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(AddProjectTaskDependencyCommand $command): void
    {
        $this->service->addDependency($command->companyId, $command->projectId, $command->predecessorId, $command->successorId, $command->actorId);
    }
}
