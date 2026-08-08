<?php

namespace Modules\Employee\Application\Commands\StartOnboarding;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class StartOnboardingHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(StartOnboardingCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->startOnboarding($command->data);
    }
}
