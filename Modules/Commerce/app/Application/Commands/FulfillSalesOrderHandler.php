<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class FulfillSalesOrderHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(FulfillSalesOrderCommand $command): int
    {
        return $this->service->fulfillSalesOrder($command->companyId, $command->actor, $command->data);
    }
}
