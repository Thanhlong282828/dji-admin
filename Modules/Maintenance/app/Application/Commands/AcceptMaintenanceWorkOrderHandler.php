<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceWorkOrderCloseoutService;

final readonly class AcceptMaintenanceWorkOrderHandler
{
    public function __construct(private MaintenanceWorkOrderCloseoutService $service) {}

    public function handle(AcceptMaintenanceWorkOrderCommand $command): int
    {
        return $this->service->accept($command->companyId, $command->workOrderId, $command->actorId, $command->note, $command->idempotencyKey);
    }
}
