<?php

namespace Modules\Onboarding\Application\Commands\CreateOnboardingCase;

use Modules\Onboarding\Application\DTOs\OnboardingCaseData;

final readonly class CreateOnboardingCaseCommand
{
    public function __construct(public OnboardingCaseData $data) {}
}
