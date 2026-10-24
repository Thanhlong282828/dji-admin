<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SalesReceivableService;

final readonly class CreateQuotationHandler
{
    public function __construct(private SalesReceivableService $service) {}

    public function handle(CreateQuotationCommand $command): int
    {
        return $this->service->createQuotation($command->companyId, $command->actor, $command->data);
    }
}
