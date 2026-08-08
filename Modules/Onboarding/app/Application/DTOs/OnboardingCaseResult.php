<?php

namespace Modules\Onboarding\Application\DTOs;

final readonly class OnboardingCaseResult
{
    public function __construct(
        public int $caseId,
        public int $checklistCount,
        public int $provisioningCount,
        public string $status,
    ) {}
}
