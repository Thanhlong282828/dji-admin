<?php

namespace Modules\Onboarding\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class OnboardingCaseData
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $applicationId,
        public int $offerId,
        public int $requisitionId,
        public int $jobPositionId,
        public int $organizationUnitId,
        public ?int $ownerUserId,
        public CarbonImmutable $startDate,
        public ?string $templateCode,
        public string $employmentType,
        public int $actorId,
    ) {}
}
