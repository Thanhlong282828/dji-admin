<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationsObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_observability_routes_enforce_authentication_and_permission(): void
    {
        $this->get('/admin/operations/observability')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/observability')->assertForbidden();
        $this->actingAs($user)->get('/admin/operations/observability/metrics')->assertForbidden();
    }

    public function test_authorized_actor_can_view_dashboard_and_safe_metrics(): void
    {
        Cache::put('operations:http:total', 100, 60);
        Cache::put('operations:http:5xx', 1, 60);
        Cache::put('operations:http:latency:count', 100, 60);
        Cache::put('operations:http:latency:sum_ms', 5000, 60);
        Cache::put('operations:http:latency:le:50', 50, 60);
        Cache::put('operations:http:latency:le:100', 95, 60);
        Cache::put('operations:http:latency:le:250', 100, 60);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/observability')->assertOk()->assertSee('Enterprise Observability');
        $response = $this->actingAs($user)->getJson('/admin/operations/observability/metrics');
        $response->assertOk()->assertJsonPath('http.p95_ms', 100)->assertJsonMissingPath('database.host');
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('DB_PASSWORD', $content);
        self::assertStringNotContainsString('Authorization', $content);
    }

    public function test_request_metrics_and_correlation_are_recorded_without_payload(): void
    {
        Cache::flush();
        $response = $this->withHeader('X-Request-ID', 'obs-request-1234')->get('/health/live');

        $response->assertOk()->assertHeader('X-Request-ID', 'obs-request-1234');
        self::assertSame(1, Cache::get('operations:http:total'));
        self::assertSame(1, Cache::get('operations:http:status:2xx'));
    }
}
