<?php

namespace Modules\Inventory\Application\Queries;

use App\Models\User;

final readonly class GetInventoryVarianceQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
