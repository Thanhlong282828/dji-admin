<?php

namespace Modules\Onboarding\Application\DTOs;

final readonly class OnboardingProvisioningTemplateItem
{
    public function __construct(
        public string $code,
        public string $title,
        public string $type,
        public string $provider,
        public string $providerStatus,
        public string $ownerType,
        public int $dueOffsetDays,
        public bool $required,
    ) {}
}
