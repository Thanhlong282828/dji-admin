<?php

namespace Modules\Leave\Application\Commands;

use Modules\Leave\Domain\Services\LeaveManagementService;

final readonly class ManageLeaveHandler
{
    public function __construct(private LeaveManagementService $service) {}

    public function handle(ManageLeaveCommand $command): int
    {
        return $this->service->execute($command);
    }
}
