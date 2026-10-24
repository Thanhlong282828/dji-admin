<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class ChangeProjectTaskStatusHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(ChangeProjectTaskStatusCommand $command): void
    {
        $this->service->changeTaskStatus($command->companyId, $command->projectId, $command->taskId, $command->status, $command->actorId);
    }
}
