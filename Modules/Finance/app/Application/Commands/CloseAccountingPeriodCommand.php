<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class CloseAccountingPeriodCommand
{
    public function __construct(public int $companyId, public int $periodId, public User $actor, public string $reason) {}
}
