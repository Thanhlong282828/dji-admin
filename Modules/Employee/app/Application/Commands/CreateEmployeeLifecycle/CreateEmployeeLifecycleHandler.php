<?php

namespace Modules\Employee\Application\Commands\CreateEmployeeLifecycle;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class CreateEmployeeLifecycleHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(CreateEmployeeLifecycleCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->create($command->data);
    }
}
