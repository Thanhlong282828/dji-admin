<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class CreatePreventiveMaintenancePlanHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(CreatePreventiveMaintenancePlanCommand $c): int
    {
        return $this->service->createPlan($c->data, $c->actorId);
    }
}
