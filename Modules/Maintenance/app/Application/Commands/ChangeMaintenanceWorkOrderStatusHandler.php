<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class ChangeMaintenanceWorkOrderStatusHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(ChangeMaintenanceWorkOrderStatusCommand $c): void
    {
        $this->service->transitionWorkOrder($c->companyId, $c->orderId, $c->status, $c->actorId, $c->data);
    }
}
