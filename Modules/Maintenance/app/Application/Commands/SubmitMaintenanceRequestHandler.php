<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class SubmitMaintenanceRequestHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(SubmitMaintenanceRequestCommand $c): void
    {
        $this->service->submitRequest($c->companyId, $c->requestId, $c->actorId);
    }
}
