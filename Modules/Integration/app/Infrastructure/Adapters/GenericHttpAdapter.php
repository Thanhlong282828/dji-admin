<?php

namespace Modules\Integration\Infrastructure\Adapters;

use Illuminate\Validation\ValidationException;
use Modules\Integration\Domain\Contracts\IntegrationAdapterInterface;
use Modules\Integration\Domain\DTOs\DeliveryResult;

final class GenericHttpAdapter implements IntegrationAdapterInterface
{
    public function type(): string
    {
        return 'http_api';
    }

    public function validateConfiguration(array $configuration): void
    {
        $url = $configuration['base_url'] ?? null;
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['base_url' => 'A valid HTTPS endpoint is required.']);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $payload
     */
    public function send(array $configuration, array $payload, string $idempotencyKey): DeliveryResult
    {
        $this->validateConfiguration($configuration);
        if (($configuration['mode'] ?? null) !== 'mock') {
            return new DeliveryResult(false, null, 'external_disabled', 'External HTTP delivery is disabled in this foundation phase.');
        }

        return new DeliveryResult(true, 'MOCK-'.$idempotencyKey, '202', 'Mock provider accepted the message.');
    }

    public function healthCheck(array $configuration): string
    {
        try {
            $this->validateConfiguration($configuration);

            return 'unknown';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }
}
