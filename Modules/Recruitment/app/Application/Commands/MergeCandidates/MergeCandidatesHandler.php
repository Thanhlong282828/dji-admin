<?php

namespace Modules\Recruitment\Application\Commands\MergeCandidates;

use Modules\Recruitment\Domain\Services\CandidateLifecycleService;

final readonly class MergeCandidatesHandler
{
    public function __construct(private CandidateLifecycleService $service) {}

    public function handle(MergeCandidatesCommand $command): void
    {
        $this->service->merge($command->companyId, $command->primaryId, $command->secondaryId, $command->actorId);
    }
}
