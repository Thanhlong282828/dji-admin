<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\IntegrationHubService;

final readonly class RetryIntegrationMessageHandler
{
    public function __construct(private IntegrationHubService $service) {}

    public function handle(RetryIntegrationMessageCommand $c): void
    {
        $this->service->retry($c->companyId, $c->messageId, $c->actorId);
    }
}
