<?php

namespace Modules\Recruitment\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class CandidateHireCompleted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $companyId,
        public int $offerId,
        public int $applicationId,
        public int $candidateId,
        public int $employeeId,
        public int $onboardingCaseId,
        public int $version,
        public int $actorId,
    ) {}
}
