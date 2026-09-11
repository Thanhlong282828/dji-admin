<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\OrderTransitionService;

final readonly class TransitionSellerOrderHandler
{
    public function __construct(private OrderTransitionService $transitions) {}

    public function handle(TransitionSellerOrderCommand $command): int
    {
        $this->transitions->sellerTransition($command->actor, $command->orderId, $command->action);

        return $command->orderId;
    }
}
