<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ServiceAcceptanceService;

final readonly class SubmitServiceAcceptanceHandler
{
    public function __construct(private ServiceAcceptanceService $service) {}

    public function handle(SubmitServiceAcceptanceCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->acceptanceId, $command->actor);
    }
}
