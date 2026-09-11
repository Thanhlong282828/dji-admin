<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class IssueCustomerInvoiceHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(IssueCustomerInvoiceCommand $command): int
    {
        return $this->service->issueCustomerInvoice($command->companyId, $command->actor, $command->data);
    }
}
