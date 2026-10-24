<?php

namespace Modules\Procurement\Application\Queries;

use App\Models\User;

final readonly class GetProcurementProcessHelpQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
