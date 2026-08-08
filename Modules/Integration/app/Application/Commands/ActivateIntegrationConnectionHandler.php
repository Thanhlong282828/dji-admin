<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\IntegrationHubService;

final readonly class ActivateIntegrationConnectionHandler
{
    public function __construct(private IntegrationHubService $service) {}

    public function handle(ActivateIntegrationConnectionCommand $c): void
    {
        $this->service->activate($c->companyId, $c->connectionId, $c->actorId);
    }
}
