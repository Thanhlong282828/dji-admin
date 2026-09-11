<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceIntegrationService;

final readonly class CreateMaintenancePurchaseRequestHandler
{
    public function __construct(private MaintenanceIntegrationService $service) {}

    public function handle(CreateMaintenancePurchaseRequestCommand $command): int
    {
        return $this->service->createPurchaseRequest($command->companyId, $command->workOrderId, $command->actor, $command->data);
    }
}
