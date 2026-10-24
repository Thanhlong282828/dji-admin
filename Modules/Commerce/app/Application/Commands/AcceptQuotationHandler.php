<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class AcceptQuotationHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(AcceptQuotationCommand $command): int
    {
        return $this->service->acceptQuotation($command->companyId, $command->actor, $command->data);
    }
}
