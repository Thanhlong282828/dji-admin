<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\VendorBillReversalService;

final readonly class RequestVendorBillReversalHandler
{
    public function __construct(private VendorBillReversalService $service) {}

    public function handle(RequestVendorBillReversalCommand $command): int
    {
        return $this->service->request($command->companyId, $command->billId, $command->targetPeriodId, $command->reason, $command->idempotencyKey, $command->actor);
    }
}
