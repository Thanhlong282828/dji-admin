<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class ChangeProjectStatusHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(ChangeProjectStatusCommand $command): void
    {
        $this->service->changeProjectStatus($command->companyId, $command->projectId, $command->status, $command->actorId);
    }
}
