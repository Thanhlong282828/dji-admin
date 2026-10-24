<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class MutateFulfillmentCommand
{
    public function __construct(public User $actor, public int $orderId, public string $operation, public ?int $branchId = null, public ?string $carrier = null, public ?string $trackingReference = null) {}
}
