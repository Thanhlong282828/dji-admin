<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class UpdateProjectHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(UpdateProjectCommand $command): void
    {
        $this->service->updateProject($command->companyId, $command->projectId, $command->data, $command->actorId);
    }
}
