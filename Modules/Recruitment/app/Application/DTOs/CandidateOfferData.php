<?php

namespace Modules\Recruitment\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class CandidateOfferData
{
    /** @param array<string, string|int|bool|null> $terms */
    public function __construct(
        public int $applicationId,
        public string $employmentType,
        public string $baseSalary,
        public string $currency,
        public CarbonImmutable $startDate,
        public ?CarbonImmutable $probationEndDate,
        public ?CarbonImmutable $expiresAt,
        public array $terms,
    ) {}
}
