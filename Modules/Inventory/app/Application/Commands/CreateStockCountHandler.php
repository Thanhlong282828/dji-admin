<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryAdjustmentService;

final readonly class CreateStockCountHandler
{
    public function __construct(private InventoryAdjustmentService $service) {}

    public function handle(CreateStockCountCommand $command): int
    {
        return $this->service->createStockCount($command->companyId, $command->actor, $command->data);
    }
}
