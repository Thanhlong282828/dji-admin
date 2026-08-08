<?php

namespace Modules\Recruitment\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class HireCandidatePreview
{
    public function __construct(
        public int $offerId,
        public int $offerVersion,
        public int $applicationId,
        public int $applicationVersion,
        public string $candidateName,
        public string $candidateEmail,
        public int $organizationUnitId,
        public string $organizationUnitName,
        public int $jobPositionId,
        public string $jobPositionTitle,
        public string $employmentType,
        public CarbonImmutable $startDate,
        public string $offerStatus,
        public string $reservationStatus,
        public ?string $baseSalary,
        public ?string $currency,
        public bool $alreadyHired,
        public ?int $employeeId,
    ) {}
}
