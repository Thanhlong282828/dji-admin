<?php

namespace Modules\Inventory\Application\Queries;

use App\Models\User;

final readonly class GetInventoryMovementListQuery
{
    public function __construct(public User $actor, public int $companyId, public int $branchId = 0, public string $type = '', public string $reference = '') {}
}
