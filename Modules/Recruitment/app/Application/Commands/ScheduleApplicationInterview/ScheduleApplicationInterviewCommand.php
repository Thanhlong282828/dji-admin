<?php

namespace Modules\Recruitment\Application\Commands\ScheduleApplicationInterview;

use Modules\Recruitment\Application\DTOs\InterviewScheduleData;

final readonly class ScheduleApplicationInterviewCommand
{
    public function __construct(public int $companyId, public InterviewScheduleData $data, public int $actorId) {}
}
