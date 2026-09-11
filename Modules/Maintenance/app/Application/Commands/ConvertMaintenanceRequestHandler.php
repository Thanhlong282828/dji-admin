<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class ConvertMaintenanceRequestHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(ConvertMaintenanceRequestCommand $c): int
    {
        return $this->service->convertRequest($c->companyId, $c->requestId, $c->actorId);
    }
}
