<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class CreateMaintenanceRequestHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(CreateMaintenanceRequestCommand $c): int
    {
        return $this->service->createRequest($c->data, $c->actorId);
    }
}
