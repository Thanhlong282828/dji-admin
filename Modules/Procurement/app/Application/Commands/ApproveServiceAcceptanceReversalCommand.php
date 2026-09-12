<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class ApproveServiceAcceptanceReversalCommand
{
    public function __construct(public int $companyId, public int $acceptanceId, public User $actor) {}
}
