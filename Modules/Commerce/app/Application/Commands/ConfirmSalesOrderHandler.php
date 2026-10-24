<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class ConfirmSalesOrderHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(ConfirmSalesOrderCommand $command): int
    {
        return $this->service->confirmSalesOrder($command->companyId, $command->actor, $command->data);
    }
}
