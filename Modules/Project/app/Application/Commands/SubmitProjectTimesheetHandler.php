<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class SubmitProjectTimesheetHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(SubmitProjectTimesheetCommand $command): void
    {
        $this->service->submitTimesheet($command->companyId, $command->projectId, $command->timesheetId, $command->actorId);
    }
}
