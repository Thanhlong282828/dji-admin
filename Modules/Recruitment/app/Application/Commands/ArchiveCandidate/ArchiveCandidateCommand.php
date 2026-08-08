<?php

namespace Modules\Recruitment\Application\Commands\ArchiveCandidate;

final readonly class ArchiveCandidateCommand
{
    public function __construct(public int $companyId, public int $candidateId, public int $actorId, public string $reason) {}
}
