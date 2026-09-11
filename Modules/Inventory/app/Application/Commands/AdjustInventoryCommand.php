<?php

namespace Modules\Inventory\Application\Commands;

use App\Models\User;

final readonly class AdjustInventoryCommand
{
    public function __construct(public int $variantId, public int $locationId, public string $operation, public int $quantity, public string $reason, public User $actor) {}
}
