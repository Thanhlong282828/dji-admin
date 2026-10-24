<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\VendorRefundService;

final readonly class PostSupplierRefundHandler
{
    public function __construct(private VendorRefundService $service) {}

    public function handle(PostSupplierRefundCommand $command): int
    {
        return $this->service->post($command->companyId, $command->receiptId, $command->actor);
    }
}
