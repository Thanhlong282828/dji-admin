<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SellerGovernanceService;

final readonly class ChangeSellerGovernanceStatusHandler
{
    public function __construct(private SellerGovernanceService $service) {}

    /** @return array{from:string,to:string} */
    public function handle(ChangeSellerGovernanceStatusCommand $command): array
    {
        return $this->service->transition($command->companyId, $command->sellerId, $command->targetStatus, $command->reason, $command->actor);
    }
}
