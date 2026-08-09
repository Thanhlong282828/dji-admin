<?php

namespace Tests\Feature;

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
}
