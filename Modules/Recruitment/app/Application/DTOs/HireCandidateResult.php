<?php

namespace Modules\Recruitment\Application\DTOs;

final readonly class HireCandidateResult
{
    public function __construct(
        public int $companyId,
        public int $candidateId,
        public int $applicationId,
        public int $offerId,
        public int $employeeId,
        public string $employeeCode,
        public int $assignmentId,
        public int $contractId,
        public int $onboardingCaseId,
        public bool $alreadyCompleted,
    ) {}
}
