<?php

namespace Modules\Recruitment\Application\Commands\TransitionApplicationInterview;

use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class TransitionApplicationInterviewHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    public function handle(TransitionApplicationInterviewCommand $command): ApplicationInterview
    {
        return $this->service->transition(
            $command->companyId,
            $command->interviewId,
            $command->target,
            $command->actorId,
            $command->reason,
            $command->expectedVersion,
        );
    }
}
