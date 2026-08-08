<?php

namespace Modules\Attendance\Application\Commands;

use Modules\Attendance\Domain\Services\WorkforceManagementService;

final readonly class ManageWorkforceResourceHandler
{
    public function __construct(private WorkforceManagementService $service) {}

    public function handle(ManageWorkforceResourceCommand $command): int
    {
        return $this->service->execute($command);
    }
}
