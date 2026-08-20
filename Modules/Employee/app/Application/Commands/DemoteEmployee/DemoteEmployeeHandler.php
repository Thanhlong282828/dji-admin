<?php

namespace Modules\Employee\Application\Commands\DemoteEmployee;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class DemoteEmployeeHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(DemoteEmployeeCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->changeAssignment($command->data);
    }
}
