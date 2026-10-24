<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class RequestVendorBillReversalCommand
{
    public function __construct(public int $companyId, public int $billId, public int $targetPeriodId, public string $reason, public string $idempotencyKey, public User $actor) {}
}
