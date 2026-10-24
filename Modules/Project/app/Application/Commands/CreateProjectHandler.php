<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class CreateProjectHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(CreateProjectCommand $command): int
    {
        return $this->service->createProject($command->data, $command->actorId);
    }
}
