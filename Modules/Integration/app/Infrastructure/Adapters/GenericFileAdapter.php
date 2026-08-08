<?php

namespace Modules\Integration\Infrastructure\Adapters;

use Modules\Integration\Domain\Contracts\IntegrationAdapterInterface;
use Modules\Integration\Domain\DTOs\DeliveryResult;

final class GenericFileAdapter implements IntegrationAdapterInterface
{
    public function type(): string
    {
        return 'file_export';
    }

    public function validateConfiguration(array $configuration): void {}

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $payload
     */
    public function send(array $configuration, array $payload, string $idempotencyKey): DeliveryResult
    {
        return new DeliveryResult(true, 'FILE-'.$idempotencyKey, 'stored', 'File export accepted by local foundation adapter.');
    }

    public function healthCheck(array $configuration): string
    {
        return 'healthy';
    }
}
