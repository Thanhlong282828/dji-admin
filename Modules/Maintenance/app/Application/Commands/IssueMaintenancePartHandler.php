<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class IssueMaintenancePartHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(IssueMaintenancePartCommand $c): void
    {
        $this->service->issuePart($c->companyId, $c->orderId, $c->partId, $c->actorId);
    }
}
