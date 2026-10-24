<?php

namespace Modules\Commerce\Application\Queries;

use App\Models\User;

final readonly class GetSalesProcessHelpQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
