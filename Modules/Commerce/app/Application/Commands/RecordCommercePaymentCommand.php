<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class RecordCommercePaymentCommand
{
    public function __construct(public User $actor, public int $companyId, public int $orderId, public string $method, public string $settlementReference, public string $idempotencyKey) {}
}
