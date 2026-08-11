<?php

namespace Modules\Integration\Infrastructure\Adapters;

use App\Support\RuntimeConfiguration\FeatureFlag;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use App\Support\Security\OutboundUrlPolicy;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Integration\Domain\Contracts\IntegrationAdapterInterface;
use Modules\Integration\Domain\DTOs\DeliveryResult;

final class GenericHttpAdapter implements IntegrationAdapterInterface
{
    public function __construct(private readonly ?RuntimeConfigurationService $configuration = null, private readonly ?OutboundUrlPolicy $urlPolicy = null) {}

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
        try {
            ($this->urlPolicy ?? app(OutboundUrlPolicy::class))->assertAllowed($url);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['base_url' => $exception->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $payload
     */
    public function send(array $configuration, array $payload, string $idempotencyKey): DeliveryResult
    {
        $runtime = $this->configuration ?? app(RuntimeConfigurationService::class);
        if (! $runtime->enabled(FeatureFlag::IntegrationOutboundDelivery)) {
            return new DeliveryResult(false, null, 'controlled_disabled', 'Outbound delivery is disabled by runtime configuration.');
        }
        $this->validateConfiguration($configuration);
        $mode = $configuration['mode'] ?? null;
        if ($mode === 'mock_timeout') {
            return new DeliveryResult(false, null, 'timeout', 'Mock provider timed out.');
        }
        if ($mode === 'mock_5xx') {
            return new DeliveryResult(false, null, '503', 'Mock provider was unavailable.');
        }
        if ($mode === 'mock_invalid_response') {
            return new DeliveryResult(false, null, 'invalid_response', 'Mock provider returned an invalid response.');
        }
        if ($mode !== 'mock') {
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
