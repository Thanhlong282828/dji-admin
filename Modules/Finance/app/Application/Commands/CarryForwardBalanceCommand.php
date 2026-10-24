<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class CarryForwardBalanceCommand
{
    public function __construct(public int $companyId, public int $closeRequestId, public int $targetFiscalYearId, public User $actor) {}
}
