<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class ApproveTreasuryInternalTransferCommand
{
    public function __construct(public int $companyId, public int $transferId, public User $actor) {}
}
