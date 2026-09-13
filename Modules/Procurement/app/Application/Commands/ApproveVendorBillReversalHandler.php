<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\VendorBillReversalService;

final readonly class ApproveVendorBillReversalHandler
{
    public function __construct(private VendorBillReversalService $service) {}

    public function handle(ApproveVendorBillReversalCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->requestId, $command->effectiveAt, $command->actor);
    }
}
