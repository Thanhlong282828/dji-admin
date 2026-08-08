<?php

namespace Modules\Recruitment\Application\Commands\CreateCandidate;

final readonly class CreateCandidateCommand
{
    /** @param array<string, mixed> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
