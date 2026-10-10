<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\VendorRefundService;

final readonly class ApproveSupplierRefundHandler
{
    public function __construct(private VendorRefundService $service) {}

    public function handle(ApproveSupplierRefundCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->receiptId, $command->actor);
    }
}
