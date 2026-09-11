<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class CreateProjectTimesheetHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(CreateProjectTimesheetCommand $command): int
    {
        return $this->service->createTimesheet($command->companyId, $command->projectId, $command->data, $command->actorId);
    }
}
