<?php

namespace Modules\Recruitment\Application\Commands\ArchiveCandidate;

use Modules\Recruitment\Domain\Services\CandidateLifecycleService;

final readonly class ArchiveCandidateHandler
{
    public function __construct(private CandidateLifecycleService $service) {}

    public function handle(ArchiveCandidateCommand $command): void
    {
        $this->service->archive($command->companyId, $command->candidateId, $command->actorId, $command->reason);
    }
}
