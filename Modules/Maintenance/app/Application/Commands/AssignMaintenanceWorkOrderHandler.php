<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class AssignMaintenanceWorkOrderHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(AssignMaintenanceWorkOrderCommand $c): void
    {
        $this->service->assign($c->companyId, $c->orderId, $c->employeeId, $c->actorId);
    }
}
