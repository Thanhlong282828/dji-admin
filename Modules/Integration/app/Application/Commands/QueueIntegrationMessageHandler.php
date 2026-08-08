<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\IntegrationHubService;

final readonly class QueueIntegrationMessageHandler
{
    public function __construct(private IntegrationHubService $service) {}

    public function handle(QueueIntegrationMessageCommand $c): int
    {
        return $this->service->queue($c->companyId, $c->connectionId, $c->actorId, $c->sourceType, $c->sourceId, $c->messageType, $c->payload, $c->idempotencyKey);
    }
}
