<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class CreateCustomerInvoiceHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(CreateCustomerInvoiceCommand $command): int
    {
        return $this->service->createCustomerInvoice($command->companyId, $command->actor, $command->data);
    }
}
