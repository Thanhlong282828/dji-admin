<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryAdjustmentService;

final readonly class ApproveStockCountHandler
{
    public function __construct(private InventoryAdjustmentService $service) {}

    public function handle(ApproveStockCountCommand $command): int
    {
        return $this->service->approveStockCount($command->companyId, $command->stockCountId, $command->actor);
    }
}
