<?php

namespace Tests\Unit\Operations;

use App\Support\GoLive\ProductionReadinessService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionReadinessServiceTest extends TestCase
{
    public function test_snapshot_is_read_only_structured_and_redacted(): void
    {
        config()->set('services.cloudflare_images.api_token', 'forbidden-secret-value');
        $snapshot = app(ProductionReadinessService::class)->snapshot();

        self::assertSame(102, $snapshot['migration_count']);
        self::assertContains($snapshot['classification'], ['STAGING_READY', 'PRODUCTION_BLOCKED_EXTERNAL']);
        self::assertSame(['total', 'passed', 'warnings', 'blocked'], array_keys($snapshot['summary']));
        self::assertStringNotContainsString('forbidden-secret-value', json_encode($snapshot, JSON_THROW_ON_ERROR));
        self::assertContains('Cloudflare Images', array_column($snapshot['dependencies'], 'name'));
    }

    public function test_json_command_reports_without_mutating_or_exposing_secrets(): void
    {
        config()->set('database.connections.mysql.password', 'forbidden-db-secret');
        self::assertSame(0, Artisan::call('operations:production-readiness', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringNotContainsString('forbidden-db-secret', $output);
        self::assertStringContainsString('PRODUCTION_BLOCKED_EXTERNAL', $output);
    }
}
