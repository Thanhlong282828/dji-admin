<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceWorkOrderCloseoutService;

final readonly class CloseMaintenanceWorkOrderHandler
{
    public function __construct(private MaintenanceWorkOrderCloseoutService $service) {}

    public function handle(CloseMaintenanceWorkOrderCommand $command): int
    {
        return $this->service->close($command->companyId, $command->workOrderId, $command->actorId, $command->idempotencyKey);
    }
}
