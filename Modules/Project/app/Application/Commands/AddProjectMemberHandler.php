<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectManagementService;

final readonly class AddProjectMemberHandler
{
    public function __construct(private ProjectManagementService $service) {}

    public function handle(AddProjectMemberCommand $command): void
    {
        $this->service->addMember($command->companyId, $command->projectId, $command->data, $command->actorId);
    }
}
