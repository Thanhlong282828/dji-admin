<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\IntegrationHubService;

final readonly class ReceiveWebhookHandler
{
    public function __construct(private IntegrationHubService $service) {}

    public function handle(ReceiveWebhookCommand $c): int
    {
        return $this->service->receiveWebhook($c->companyId, $c->connectionId, $c->provider, $c->eventId, $c->eventType, $c->payload, $c->signature);
    }
}
