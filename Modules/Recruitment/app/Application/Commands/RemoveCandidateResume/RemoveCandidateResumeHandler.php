<?php

namespace Modules\Recruitment\Application\Commands\RemoveCandidateResume;

use Modules\Recruitment\Domain\Services\CandidateResumeService;

final readonly class RemoveCandidateResumeHandler
{
    public function __construct(private CandidateResumeService $service) {}

    public function handle(RemoveCandidateResumeCommand $command): void
    {
        $this->service->remove($command->companyId, $command->candidateId, $command->actorId);
    }
}
