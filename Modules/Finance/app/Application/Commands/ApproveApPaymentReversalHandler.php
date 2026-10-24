<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\ApPaymentReversalService;

final readonly class ApproveApPaymentReversalHandler
{
    public function __construct(private ApPaymentReversalService $service) {}

    public function handle(ApproveApPaymentReversalCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->requestId, $command->effectiveAt, $command->actor);
    }
}
