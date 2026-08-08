<?php

namespace Modules\Employee\Application\Commands\CompleteOnboarding;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class CompleteOnboardingHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(CompleteOnboardingCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->completeOnboarding($command->data);
    }
}
