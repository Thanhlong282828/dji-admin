<?php

namespace Modules\Recruitment\Application\Commands\ScheduleApplicationInterview;

use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class ScheduleApplicationInterviewHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    public function handle(ScheduleApplicationInterviewCommand $command): ApplicationInterview
    {
        return $this->service->schedule($command->companyId, $command->data, $command->actorId);
    }
}
