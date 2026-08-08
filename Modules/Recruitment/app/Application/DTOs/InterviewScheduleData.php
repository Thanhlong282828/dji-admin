<?php

namespace Modules\Recruitment\Application\DTOs;

use Carbon\CarbonImmutable;
use Modules\Recruitment\Domain\Enums\InterviewType;

final readonly class InterviewScheduleData
{
    /** @param list<int> $interviewerUserIds */
    public function __construct(
        public int $applicationId,
        public string $title,
        public InterviewType $type,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $timezone,
        public ?string $location,
        public ?string $meetingUrl,
        public ?string $instructions,
        public int $roundNumber,
        public array $interviewerUserIds,
        public ?int $expectedApplicationVersion,
    ) {}
}
