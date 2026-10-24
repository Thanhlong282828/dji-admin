<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class TransitionSellerOrderCommand
{
    public function __construct(public User $actor, public int $orderId, public string $action) {}
}
