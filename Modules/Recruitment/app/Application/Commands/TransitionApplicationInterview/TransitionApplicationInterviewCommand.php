<?php

namespace Modules\Recruitment\Application\Commands\TransitionApplicationInterview;

use Modules\Recruitment\Domain\Enums\InterviewStatus;

final readonly class TransitionApplicationInterviewCommand
{
    public function __construct(
        public int $companyId,
        public int $interviewId,
        public InterviewStatus $target,
        public int $actorId,
        public ?string $reason,
        public ?int $expectedVersion,
    ) {}
}
