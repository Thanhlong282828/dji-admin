<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class ApproveTreasuryOpeningBalanceCommand
{
    public function __construct(public int $companyId, public int $openingBalanceId, public User $actor) {}
}
