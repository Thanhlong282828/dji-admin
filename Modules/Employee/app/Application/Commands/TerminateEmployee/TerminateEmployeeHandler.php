<?php

namespace Modules\Employee\Application\Commands\TerminateEmployee;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class TerminateEmployeeHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(TerminateEmployeeCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->terminate($command->data);
    }
}
