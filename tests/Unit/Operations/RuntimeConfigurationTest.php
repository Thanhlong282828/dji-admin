<?php

namespace Tests\Unit\Operations;

use App\Support\RuntimeConfiguration\FeatureFlag;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Modules\Integration\Infrastructure\Adapters\GenericHttpAdapter;
use Tests\TestCase;

final class RuntimeConfigurationTest extends TestCase
{
    public function test_typed_flags_have_deterministic_snapshot_and_safe_precedence(): void
    {
        $this->setFlag(FeatureFlag::IntegrationOutboundDelivery, false);
        $cache = new Repository(new ArrayStore);
        $cache->put('runtime-config:integration.outbound_delivery', true, 60);
        $service = new RuntimeConfigurationService($cache);

        self::assertTrue($service->enabled(FeatureFlag::IntegrationOutboundDelivery));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $service->snapshotChecksum());
        self::assertSame($service->snapshotChecksum(), $service->snapshotChecksum());
    }

    public function test_redis_failure_falls_back_to_safe_environment_value(): void
    {
        $this->setFlag(FeatureFlag::OperationsObservability, true);
        $this->setFlag(FeatureFlag::IntegrationOutboundDelivery, true);
        $cache = new class(new ArrayStore) extends Repository
        {
            /** @param string|array<string> $key */
            public function get($key, $default = null): mixed
            {
                throw new \RuntimeException('redis unavailable at internal-host');
            }
        };
        $service = new RuntimeConfigurationService($cache);

        self::assertTrue($service->enabled(FeatureFlag::OperationsObservability));
        self::assertFalse($service->enabled(FeatureFlag::IntegrationOutboundDelivery));
        self::assertGreaterThanOrEqual(2, $service->metrics()['evaluation_failure_count']);
    }

    public function test_integration_kill_switch_blocks_external_side_effect(): void
    {
        $this->setFlag(FeatureFlag::IntegrationOutboundDelivery, false);
        $result = app(GenericHttpAdapter::class)->send(['base_url' => 'https://example.invalid', 'mode' => 'mock'], [], 'same-intent');

        self::assertFalse($result->delivered);
        self::assertSame('controlled_disabled', $result->responseCode);
        self::assertNull($result->externalReference);
    }

    public function test_integration_can_be_reenabled_without_duplicate_external_identity(): void
    {
        $this->setFlag(FeatureFlag::IntegrationOutboundDelivery, true);
        $adapter = app(GenericHttpAdapter::class);
        $first = $adapter->send(['base_url' => 'https://example.invalid', 'mode' => 'mock'], [], 'same-intent');
        $second = $adapter->send(['base_url' => 'https://example.invalid', 'mode' => 'mock'], [], 'same-intent');

        self::assertTrue($first->delivered);
        self::assertSame('MOCK-same-intent', $first->externalReference);
        self::assertSame($first->externalReference, $second->externalReference);
    }

    public function test_effective_dating_and_invalid_override_fall_back_deterministically(): void
    {
        $this->setFlag(FeatureFlag::OperationsObservability, true);
        $this->setFlag(FeatureFlag::IntegrationOutboundDelivery, true);
        $cache = new Repository(new ArrayStore);
        $cache->put('runtime-config:operations.observability', ['enabled' => false, 'effective_until' => '2000-01-01T00:00:00Z'], 60);
        $cache->put('runtime-config:integration.outbound_delivery', ['enabled' => 'not-a-bool'], 60);
        $service = new RuntimeConfigurationService($cache);

        self::assertTrue($service->enabled(FeatureFlag::OperationsObservability));
        self::assertFalse($service->enabled(FeatureFlag::IntegrationOutboundDelivery));
        self::assertSame('drift', $service->resolvedEntry(FeatureFlag::IntegrationOutboundDelivery)->driftStatus);
    }

    private function setFlag(FeatureFlag $flag, bool $enabled): void
    {
        $flags = config('runtime-configuration.flags');
        self::assertIsArray($flags);
        $entry = $flags[$flag->value] ?? [];
        self::assertIsArray($entry);
        $entry['enabled'] = $enabled;
        $flags[$flag->value] = $entry;
        config()->set('runtime-configuration.flags', $flags);
    }
}
