<?php

namespace Modules\Onboarding\Domain\Services;

use Modules\Onboarding\Application\DTOs\OnboardingProvisioningTemplateItem;

interface OnboardingProvisioningTemplateProviderInterface
{
    /** @return list<OnboardingProvisioningTemplateItem> */
    public function items(?string $templateCode, string $employmentType): array;
}
