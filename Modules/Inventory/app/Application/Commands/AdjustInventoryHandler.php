<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Domain\Services\InventoryService;

final readonly class AdjustInventoryHandler
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(AdjustInventoryCommand $command): int
    {
        $this->inventory->adjust($command->variantId, $command->locationId, $command->operation, $command->quantity, [
            'source_type' => 'manual_adjustment',
            'actor_id' => $command->actor->id,
            'reason' => $command->reason,
        ]);
        activity('commerce.inventory')->causedBy($command->actor)->withProperties(['variant_id' => $command->variantId, 'operation' => $command->operation, 'quantity' => $command->quantity, 'reason' => $command->reason])->log('inventory.adjusted');

        return $command->variantId;
    }
}
