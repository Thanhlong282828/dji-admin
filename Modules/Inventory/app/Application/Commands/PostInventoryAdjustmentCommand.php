<?php

namespace Modules\Inventory\Application\Commands;

use App\Models\User;

final readonly class PostInventoryAdjustmentCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public User $actor) {}
}
