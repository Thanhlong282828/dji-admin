<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class ChangeSellerGovernanceStatusCommand
{
    public function __construct(public int $companyId, public int $sellerId, public string $targetStatus, public ?string $reason, public User $actor) {}
}
