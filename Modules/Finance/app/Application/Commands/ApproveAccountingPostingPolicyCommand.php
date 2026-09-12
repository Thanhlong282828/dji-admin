<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class ApproveAccountingPostingPolicyCommand
{
    public function __construct(public int $companyId, public int $policyId, public User $actor) {}
}
