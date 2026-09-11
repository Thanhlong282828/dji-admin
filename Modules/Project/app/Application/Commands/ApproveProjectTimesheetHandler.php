<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class ApproveProjectTimesheetHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(ApproveProjectTimesheetCommand $command): void
    {
        $this->service->approveTimesheet($command->companyId, $command->projectId, $command->timesheetId, $command->actorId, $command->approve);
    }
}
