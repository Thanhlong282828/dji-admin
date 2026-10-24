<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ServiceAcceptanceService;

final readonly class ApproveServiceAcceptanceHandler
{
    public function __construct(private ServiceAcceptanceService $service) {}

    public function handle(ApproveServiceAcceptanceCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->acceptanceId, $command->actor);
    }
}
