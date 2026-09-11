<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CommercePaymentService;

final readonly class RecordCommercePaymentHandler
{
    public function __construct(private CommercePaymentService $payments) {}

    public function handle(RecordCommercePaymentCommand $command): int
    {
        return $this->payments->recordSucceeded($command->actor, $command->companyId, $command->orderId, $command->method, $command->settlementReference, $command->idempotencyKey);
    }
}
