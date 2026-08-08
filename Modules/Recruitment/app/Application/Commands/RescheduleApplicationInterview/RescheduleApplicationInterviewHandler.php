<?php

namespace Modules\Recruitment\Application\Commands\RescheduleApplicationInterview;

use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class RescheduleApplicationInterviewHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    public function handle(RescheduleApplicationInterviewCommand $command): ApplicationInterview
    {
        return $this->service->reschedule($command->companyId, $command->interviewId, $command->startsAt, $command->endsAt, $command->timezone, $command->actorId, $command->expectedVersion, $command->reason);
    }
}
