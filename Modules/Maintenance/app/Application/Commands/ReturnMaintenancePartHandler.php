<?php

namespace Modules\Maintenance\Application\Commands;

use Modules\Maintenance\Application\Services\MaintenanceService;

final readonly class ReturnMaintenancePartHandler
{
    public function __construct(private MaintenanceService $service) {}

    public function handle(ReturnMaintenancePartCommand $command): int
    {
        return $this->service->returnPart($command->companyId, $command->orderId, $command->partId, $command->actorId, $command->data);
    }
}
