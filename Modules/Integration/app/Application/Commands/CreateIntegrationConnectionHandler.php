<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\IntegrationHubService;

final readonly class CreateIntegrationConnectionHandler
{
    public function __construct(private IntegrationHubService $service) {}

    public function handle(CreateIntegrationConnectionCommand $c): int
    {
        return $this->service->createConnection($c->companyId, $c->actorId, $c->data);
    }
}
