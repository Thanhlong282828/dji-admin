<?php

namespace Modules\Recruitment\Application\Commands\RestoreCandidate;

final readonly class RestoreCandidateCommand
{
    public function __construct(public int $companyId, public int $candidateId, public int $actorId) {}
}
