<?php

namespace Modules\Employee\Application\Commands\PromoteEmployee;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class PromoteEmployeeHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(PromoteEmployeeCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->changeAssignment($command->data);
    }
}
