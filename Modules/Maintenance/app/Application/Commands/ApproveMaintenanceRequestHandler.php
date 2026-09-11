<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class ApproveMaintenanceRequestHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(ApproveMaintenanceRequestCommand $c): void
    {
        $this->service->approveRequest($c->companyId, $c->requestId, $c->actorId, $c->approve);
    }
}
