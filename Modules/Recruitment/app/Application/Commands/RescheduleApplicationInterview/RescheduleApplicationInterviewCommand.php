<?php

namespace Modules\Recruitment\Application\Commands\RescheduleApplicationInterview;

use Carbon\CarbonImmutable;

final readonly class RescheduleApplicationInterviewCommand
{
    public function __construct(public int $companyId, public int $interviewId, public CarbonImmutable $startsAt, public CarbonImmutable $endsAt, public string $timezone, public int $actorId, public int $expectedVersion, public string $reason) {}
}
