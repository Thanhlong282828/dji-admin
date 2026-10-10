<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryAdjustmentService;

final readonly class SubmitStockCountHandler
{
    public function __construct(private InventoryAdjustmentService $service) {}

    public function handle(SubmitStockCountCommand $command): int
    {
        return $this->service->submitStockCount($command->companyId, $command->stockCountId, $command->actor);
    }
}
