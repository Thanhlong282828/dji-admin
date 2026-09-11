<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class CreateMaintenanceWorkOrderHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(CreateMaintenanceWorkOrderCommand $c): int
    {
        return $this->service->createWorkOrder($c->data, $c->actorId);
    }
}
