<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\IntegrationHubService;

final readonly class ReplayDeadLetterHandler
{
    public function __construct(private IntegrationHubService $service) {}

    public function handle(ReplayDeadLetterCommand $c): void
    {
        $this->service->replay($c->companyId, $c->deadLetterId, $c->actorId, $c->idempotencyKey);
    }
}
