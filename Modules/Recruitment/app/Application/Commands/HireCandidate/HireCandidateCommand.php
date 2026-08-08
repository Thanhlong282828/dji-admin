<?php

namespace Modules\Recruitment\Application\Commands\HireCandidate;

use Modules\Recruitment\Application\DTOs\HireCandidateData;

final readonly class HireCandidateCommand
{
    public function __construct(
        public int $companyId,
        public int $offerId,
        public HireCandidateData $data,
        public int $actorId,
    ) {}
}
