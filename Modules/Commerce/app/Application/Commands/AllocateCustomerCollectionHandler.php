<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class AllocateCustomerCollectionHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(AllocateCustomerCollectionCommand $command): int
    {
        return $this->service->allocateCustomerCollection($command->companyId, $command->actor, $command->data);
    }
}
