<?php

namespace Modules\Inventory\Application\Commands;

use App\Models\User;

final readonly class SubmitStockCountCommand
{
    public function __construct(public int $companyId, public int $stockCountId, public User $actor) {}
}
