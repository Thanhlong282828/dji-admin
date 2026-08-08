<?php

namespace Modules\Onboarding\Application\DTOs;

final readonly class OnboardingChecklistTemplateItem
{
    /** @param list<string> $dependencies */
    public function __construct(
        public string $code,
        public string $title,
        public ?string $description,
        public string $category,
        public string $ownerType,
        public int $dueOffsetDays,
        public bool $required,
        public int $sortOrder,
        public array $dependencies = [],
    ) {}
}
