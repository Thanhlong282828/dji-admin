<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class PostTreasuryInternalTransferCommand
{
    public function __construct(public int $companyId, public int $transferId, public User $actor) {}
}
