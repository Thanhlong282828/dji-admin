<?php

namespace Modules\Tax\Application\Commands;

use App\Models\User;

final readonly class ActivateVatPolicyCommand
{
    public function __construct(public int $companyId, public int $policyId, public User $actor) {}
}
