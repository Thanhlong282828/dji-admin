<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\VendorRefundService;

final readonly class SubmitSupplierRefundHandler
{
    public function __construct(private VendorRefundService $service) {}

    public function handle(SubmitSupplierRefundCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->receiptId, $command->actor);
    }
}
