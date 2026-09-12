<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class RequestServiceAcceptanceReversalCommand
{
    public function __construct(public int $companyId, public int $acceptanceId, public int $targetPeriodId, public string $reason, public string $idempotencyKey, public User $actor) {}
}
