<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\ApPaymentReversalService;

final readonly class RequestApPaymentReversalHandler
{
    public function __construct(private ApPaymentReversalService $service) {}

    public function handle(RequestApPaymentReversalCommand $command): int
    {
        return $this->service->request($command->companyId, $command->paymentId, $command->targetPeriodId, $command->reason, $command->idempotencyKey, $command->actor);
    }
}
