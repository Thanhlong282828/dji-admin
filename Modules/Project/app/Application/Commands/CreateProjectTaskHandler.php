<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class CreateProjectTaskHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(CreateProjectTaskCommand $command): int
    {
        return $this->service->createTask($command->companyId, $command->projectId, $command->data, $command->actorId);
    }
}
