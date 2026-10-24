<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\VendorRefundService;

final readonly class CreateSupplierRefundHandler
{
    public function __construct(private VendorRefundService $service) {}

    public function handle(CreateSupplierRefundCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
