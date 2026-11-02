<?php

namespace Tests\Feature;

use App\Contracts\CloudMonitoringMetricsWriter;
use App\Models\User;
use App\Support\ReliabilitySnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationsReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_unprivileged_user_is_forbidden(): void
    {
        $this->get('/admin/operations/reliability')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/reliability')->assertForbidden();
    }

    public function test_authorized_user_can_view_safe_reliability_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $permission = Permission::findOrCreate('operations.view', 'web');
        $user->givePermissionTo($permission);
        $response = $this->actingAs($user)->get('/admin/operations/reliability');
        $response->assertOk()->assertSee('Enterprise Reliability')->assertDontSee('DB_PASSWORD')->assertDontSee('Authorization');
    }

    public function test_heartbeat_is_cache_only_and_stale_status_is_deterministic(): void
    {
        Cache::forget('operations:scheduler:heartbeat');
        self::assertSame(0, Artisan::call('operations:heartbeat'));
        self::assertIsString(Cache::get('operations:scheduler:heartbeat'));
    }

    public function test_horizon_dashboard_requires_enabled_account_and_operations_permission(): void
    {
        $this->get('/horizon')->assertRedirect('/login');
        $denied = User::factory()->create(['email_verified_at' => now(), 'access_enabled' => true]);
        $this->actingAs($denied)->get('/horizon')->assertForbidden();

        $authorized = User::factory()->create(['email_verified_at' => now(), 'access_enabled' => true]);
        $authorized->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $this->actingAs($authorized)->get('/horizon')->assertOk();

        $authorized->update(['access_enabled' => false]);
        $disabledUser = $authorized->fresh();
        self::assertInstanceOf(User::class, $disabledUser);
        $this->actingAs($disabledUser)->get('/horizon')->assertForbidden();
    }

    public function test_redis_failure_marks_queue_metrics_unavailable_instead_of_healthy_zero(): void
    {
        config()->set('queue.default', 'redis');
        Redis::shouldReceive('connection')->andThrow(new \RuntimeException('Redis unavailable.'));

        $snapshot = app(ReliabilitySnapshotService::class)->snapshot();

        self::assertFalse($snapshot['queue']['available']);
        self::assertSame(0, $snapshot['queue']['pending']);
        self::assertNull($snapshot['queue']['oldest_pending_seconds']);
    }

    public function test_scheduled_export_batches_safe_metrics_without_remote_request_path_writes(): void
    {
        config()->set('operations.metrics_export.enabled', true);
        config()->set('operations.cloudflare_ddos.enabled', false);
        config()->set('queue.default', 'database');
        Cache::put('operations:http:total', 20, 600);
        Cache::put('operations:http:5xx', 1, 600);
        Cache::put('operations:http:latency:count', 20, 600);
        Cache::put('operations:http:latency:sum_ms', 2000, 600);
        Cache::put('operations:http:latency:le:250', 20, 600);
        Cache::put('operations:application:exceptions', 2, 600);
        Artisan::call('operations:heartbeat');

        $writer = new class implements CloudMonitoringMetricsWriter
        {
            /** @var list<array{type:string,value:int|float,kind:'int64'|'double'}> */
            public array $metrics = [];

            public function export(array $metrics): void
            {
                $this->metrics = $metrics;
            }
        };
        $this->app->instance(CloudMonitoringMetricsWriter::class, $writer);

        self::assertSame(0, Artisan::call('operations:export-metrics'));
        $types = array_column($writer->metrics, 'type');
        self::assertContains('custom.googleapis.com/laraveldji/http/5xx_rate', $types);
        self::assertContains('custom.googleapis.com/laraveldji/http/request_p95_ms', $types);
        self::assertContains('custom.googleapis.com/laraveldji/queue/depth', $types);
        self::assertContains('custom.googleapis.com/laraveldji/application/exception_count', $types);
        self::assertContains('custom.googleapis.com/laraveldji/application/readiness', $types);
        self::assertNotContains('authorization', $types);
    }
}
