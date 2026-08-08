<?php

namespace Modules\Recruitment\Application\Commands\AssignInterviewers;

use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class AssignInterviewersHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    public function handle(AssignInterviewersCommand $command): void
    {
        $this->service->assignInterviewers($command->companyId, $command->interviewId, $command->userIds, $command->actorId);
    }
}
