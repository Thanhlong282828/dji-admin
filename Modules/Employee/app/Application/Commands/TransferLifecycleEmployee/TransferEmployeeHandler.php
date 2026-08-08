<?php

namespace Modules\Employee\Application\Commands\TransferLifecycleEmployee;

use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;

final readonly class TransferEmployeeHandler
{
    public function __construct(private EmployeeLifecycleService $service) {}

    public function handle(TransferEmployeeCommand $command): EmployeeLifecycleEvent
    {
        return $this->service->changeAssignment($command->data);
    }
}
