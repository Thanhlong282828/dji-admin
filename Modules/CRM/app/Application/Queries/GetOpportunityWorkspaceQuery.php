<?php

namespace Modules\CRM\Application\Queries;

use App\Models\User;

final readonly class GetOpportunityWorkspaceQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor, public ?string $search = null, public ?string $stage = null) {}
}
