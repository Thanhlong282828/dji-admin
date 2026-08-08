<?php

namespace Modules\Integration\Domain\Contracts;

use Modules\Integration\Domain\DTOs\DeliveryResult;

interface IntegrationAdapterInterface
{
    public function type(): string;

    /** @param array<string,mixed> $configuration */
    public function validateConfiguration(array $configuration): void;

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $payload
     */
    public function send(array $configuration, array $payload, string $idempotencyKey): DeliveryResult;

    /** @param array<string,mixed> $configuration */
    public function healthCheck(array $configuration): string;
}
