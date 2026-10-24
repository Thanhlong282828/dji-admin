<?php

namespace Modules\Inventory\Application\Commands;

use App\Models\User;

final readonly class ApproveInventoryAdjustmentCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public User $actor) {}
}
