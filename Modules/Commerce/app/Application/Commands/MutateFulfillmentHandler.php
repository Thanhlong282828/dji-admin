<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CommerceFulfillmentService;

final readonly class MutateFulfillmentHandler
{
    public function __construct(private CommerceFulfillmentService $fulfillments) {}

    public function handle(MutateFulfillmentCommand $command): int
    {
        if ($command->operation === 'create') {
            abort_unless($command->branchId !== null, 422);

            return $this->fulfillments->create($command->actor, $command->orderId, $command->branchId);
        }
        $this->fulfillments->transition($command->actor, $command->orderId, $command->operation, $command->carrier, $command->trackingReference);

        return $command->orderId;
    }
}
