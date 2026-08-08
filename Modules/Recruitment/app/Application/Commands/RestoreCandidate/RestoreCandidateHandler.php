<?php

namespace Modules\Recruitment\Application\Commands\RestoreCandidate;

use Modules\Recruitment\Domain\Services\CandidateLifecycleService;

final readonly class RestoreCandidateHandler
{
    public function __construct(private CandidateLifecycleService $service) {}

    public function handle(RestoreCandidateCommand $command): void
    {
        $this->service->restore($command->companyId, $command->candidateId, $command->actorId);
    }
}
