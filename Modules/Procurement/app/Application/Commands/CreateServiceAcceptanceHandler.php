<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ServiceAcceptanceService;

final readonly class CreateServiceAcceptanceHandler
{
    public function __construct(private ServiceAcceptanceService $service) {}

    public function handle(CreateServiceAcceptanceCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
