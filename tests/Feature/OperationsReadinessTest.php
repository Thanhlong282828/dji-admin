<?php

namespace Tests\Feature;

use App\Support\HttpMetricsRecorder;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;
use Tests\TestCase;

final class OperationsReadinessTest extends TestCase
{
    public function test_liveness_has_safe_contract_and_request_correlation(): void
    {
        $response = $this->withHeader('X-Request-ID', 'ops-test-request-123')->getJson('/health/live');

        $response->assertOk()->assertJsonPath('status', 'alive');
        $response->assertHeader('X-Request-ID', 'ops-test-request-123');
        $payload = $response->json();
        self::assertIsArray($payload);
        self::assertArrayNotHasKey('database', $payload);
    }

    public function test_degraded_readiness_reports_safe_component_booleans(): void
    {
        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', 1);
        config()->set('database.connections.mysql.options', [\PDO::ATTR_TIMEOUT => 1]);
        config()->set('database.connections.mysql.database', 'schema_admin_testing');
        config()->set('database.default', 'mysql');
        config()->set('database.redis.default.host', '127.0.0.1');
        config()->set('database.redis.default.port', 1);
        config()->set('queue.default', 'sync');

        $payload = $this->getJson('/health/ready')->assertStatus(503)->json();

        self::assertIsArray($payload);
        self::assertSame('degraded', $payload['status']);
        self::assertSame(['database' => false, 'redis' => false, 'queue' => false], $payload['checks']);
        self::assertStringNotContainsString('password', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_telemetry_failure_preserves_health_contract_and_safe_payloads(): void
    {
        $cache = $this->createMock(Repository::class);
        $cache->method('add')->willThrowException(new RuntimeException('synthetic telemetry connection failure'));
        $this->app->instance(Repository::class, $cache);
        $this->app->forgetInstance(HttpMetricsRecorder::class);

        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', 1);
        config()->set('database.connections.mysql.options', [\PDO::ATTR_TIMEOUT => 1]);
        config()->set('database.connections.mysql.database', 'schema_admin_testing');
        config()->set('database.default', 'mysql');
        config()->set('database.redis.default.host', '127.0.0.1');
        config()->set('database.redis.default.port', 1);
        config()->set('queue.default', 'redis');

        $this->getJson('/health/live')->assertOk()->assertJsonPath('status', 'alive');
        $ready = $this->getJson('/health/ready')->assertStatus(503);
        $metrics = $this->getJson('/health/metrics-readiness')->assertStatus(503);

        foreach ([$ready->getContent(), $metrics->getContent()] as $payload) {
            self::assertIsString($payload);
            self::assertStringNotContainsString('127.0.0.1', $payload);
            self::assertStringNotContainsString('password', $payload);
            self::assertStringNotContainsString('exception', $payload);
            self::assertStringNotContainsString('stack', $payload);
        }
    }
}
