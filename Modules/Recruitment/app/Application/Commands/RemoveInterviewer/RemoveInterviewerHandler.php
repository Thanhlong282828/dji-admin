<?php

namespace Modules\Recruitment\Application\Commands\RemoveInterviewer;

use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class RemoveInterviewerHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    public function handle(RemoveInterviewerCommand $command): void
    {
        $this->service->removeInterviewer($command->companyId, $command->interviewId, $command->userId, $command->actorId);
    }
}
