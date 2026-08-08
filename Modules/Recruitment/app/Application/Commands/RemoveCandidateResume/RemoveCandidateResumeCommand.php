<?php

namespace Modules\Recruitment\Application\Commands\RemoveCandidateResume;

final readonly class RemoveCandidateResumeCommand
{
    public function __construct(public int $companyId, public int $candidateId, public int $actorId) {}
}
