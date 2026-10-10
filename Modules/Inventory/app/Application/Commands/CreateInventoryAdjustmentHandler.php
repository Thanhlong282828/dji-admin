<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryAdjustmentService;

final readonly class CreateInventoryAdjustmentHandler
{
    public function __construct(private InventoryAdjustmentService $service) {}

    public function handle(CreateInventoryAdjustmentCommand $command): int
    {
        return $this->service->createAdjustment($command->companyId, $command->actor, $command->data);
    }
}
