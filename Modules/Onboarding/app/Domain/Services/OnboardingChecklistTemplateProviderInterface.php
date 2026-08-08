<?php

namespace Modules\Onboarding\Domain\Services;

use Modules\Onboarding\Application\DTOs\OnboardingChecklistTemplateItem;

interface OnboardingChecklistTemplateProviderInterface
{
    /** @return list<OnboardingChecklistTemplateItem> */
    public function items(?string $templateCode, string $employmentType): array;
}
