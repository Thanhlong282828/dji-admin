<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class RequestApPaymentReversalCommand
{
    public function __construct(public int $companyId, public int $paymentId, public int $targetPeriodId, public string $reason, public string $idempotencyKey, public User $actor) {}
}
