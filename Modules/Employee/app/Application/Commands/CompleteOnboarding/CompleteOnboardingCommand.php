<?php

namespace Modules\Employee\Application\Commands\CompleteOnboarding;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class CompleteOnboardingCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
