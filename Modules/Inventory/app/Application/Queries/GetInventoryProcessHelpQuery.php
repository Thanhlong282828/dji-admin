<?php

namespace Modules\Inventory\Application\Queries;

use App\Models\User;

final readonly class GetInventoryProcessHelpQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
