<?php

namespace Modules\Finance\Application\Queries;

use App\Models\User;

final readonly class GetAssetLifecycleQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
