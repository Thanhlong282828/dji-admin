<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class ConvertQuotationToSalesOrderHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(ConvertQuotationToSalesOrderCommand $command): int
    {
        return $this->service->convertQuotationToSalesOrder($command->companyId, $command->actor, $command->data);
    }
}
