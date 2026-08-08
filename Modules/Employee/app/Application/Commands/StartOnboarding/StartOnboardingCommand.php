<?php

namespace Modules\Employee\Application\Commands\StartOnboarding;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class StartOnboardingCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
