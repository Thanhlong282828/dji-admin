<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class AddMaintenancePartHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(AddMaintenancePartCommand $c): int
    {
        return $this->service->requirePart($c->companyId, $c->orderId, $c->data, $c->actorId);
    }
}
