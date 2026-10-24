<?php

namespace Modules\Finance\Application\Queries;

use App\Models\User;

final readonly class GetAccountingPolicyWorkspaceQuery
{
    public function __construct(public int $companyId, public ?int $policyId = null, public ?User $actor = null) {}
}
