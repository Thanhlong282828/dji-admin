<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryAdjustmentService;

final readonly class ApproveInventoryAdjustmentHandler
{
    public function __construct(private InventoryAdjustmentService $service) {}

    public function handle(ApproveInventoryAdjustmentCommand $command): int
    {
        return $this->service->approveAdjustment($command->companyId, $command->adjustmentId, $command->actor);
    }
}
