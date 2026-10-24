<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectIntegrationService;

final readonly class CreateProjectPurchaseRequestHandler
{
    public function __construct(private ProjectIntegrationService $service) {}

    public function handle(CreateProjectPurchaseRequestCommand $command): int
    {
        return $this->service->createPurchaseRequest($command->companyId, $command->projectId, $command->actor, $command->data);
    }
}
