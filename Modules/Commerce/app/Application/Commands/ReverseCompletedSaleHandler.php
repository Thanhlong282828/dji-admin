<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SaleReversalService;

final readonly class ReverseCompletedSaleHandler
{
    public function __construct(private SaleReversalService $service) {}

    public function handle(ReverseCompletedSaleCommand $command): int
    {
        return $this->service->reverse($command->actor, $command->companyId, $command->source, $command->sourceId, $command->reason);
    }
}
