<?php

namespace Modules\Integration\Application\Commands;

final readonly class ReceiveWebhookCommand
{
    /** @param array<string,mixed> $payload */
    public function __construct(public int $companyId, public int $connectionId, public string $provider, public string $eventId, public string $eventType, public array $payload, public string $signature) {}
}
