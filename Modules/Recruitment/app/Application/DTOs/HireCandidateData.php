<?php

namespace Modules\Recruitment\Application\DTOs;

use Carbon\CarbonImmutable;
use Modules\Employee\Domain\Enums\ContractType;
use Modules\Employee\Domain\Enums\EmploymentType;

final readonly class HireCandidateData
{
    public function __construct(
        public int $expectedOfferVersion,
        public int $expectedApplicationVersion,
        public ?string $employeeCode,
        public CarbonImmutable $startDate,
        public int $organizationUnitId,
        public int $jobPositionId,
        public EmploymentType $employmentType,
        public ?string $workLocation,
        public ?int $managerUserId,
        public ContractType $contractType,
        public ?CarbonImmutable $contractEndDate,
        public ?string $onboardingTemplateCode,
        public string $reason,
        public string $idempotencyKey,
    ) {}
}
