<?php

namespace Modules\Integration\Application\Commands;

final readonly class QueueIntegrationMessageCommand
{
    /** @param array<string,mixed> $payload */
    public function __construct(public int $companyId, public int $connectionId, public int $actorId, public string $sourceType, public ?int $sourceId, public string $messageType, public array $payload, public string $idempotencyKey) {}
}
