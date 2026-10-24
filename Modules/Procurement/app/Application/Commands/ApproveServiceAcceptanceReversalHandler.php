<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ServiceAcceptanceService;

final readonly class ApproveServiceAcceptanceReversalHandler
{
    public function __construct(private ServiceAcceptanceService $service) {}

    public function handle(ApproveServiceAcceptanceReversalCommand $command): int
    {
        return $this->service->approveReversal($command->companyId, $command->acceptanceId, $command->actor);
    }
}
