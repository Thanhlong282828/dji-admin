<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryAdjustmentService;

final readonly class PostInventoryAdjustmentHandler
{
    public function __construct(private InventoryAdjustmentService $service) {}

    public function handle(PostInventoryAdjustmentCommand $command): int
    {
        return $this->service->postAdjustment($command->companyId, $command->adjustmentId, $command->actor);
    }
}
