<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        config([
            'operations.cloudflare_ddos.api_token' => 'test-token',
            'operations.cloudflare_ddos.zone_id' => 'test-zone',
            'operations.cloudflare_ddos.event_threshold' => 100,
        ]);
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.cloudflare.com/client/v4/graphql') {
                return Http::response(['data' => ['viewer' => ['zones' => [['firewallEventsAdaptiveGroups' => [['count' => 120, 'dimensions' => ['action' => 'block']]]]]]]]);
            }

            return Http::response(['success' => true, 'result' => ['id' => 'security_level', 'value' => $request->method() === 'PATCH' ? 'under_attack' : 'high', 'editable' => true]]);
        });
        Cache::put('operations:http:total', 100, 60);
        Cache::put('operations:http:5xx', 1, 60);
        Cache::put('operations:http:latency:count', 100, 60);
        Cache::put('operations:http:latency:sum_ms', 5000, 60);
        Cache::put('operations:http:latency:le:50', 50, 60);
        Cache::put('operations:http:latency:le:100', 95, 60);
        Cache::put('operations:http:latency:le:250', 100, 60);
        Cache::put('operations:storage:upload:supabase:total', 4, 60);
        Cache::put('operations:storage:upload:supabase:success', 3, 60);
        Cache::put('operations:storage:upload:supabase:failure', 1, 60);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $response = $this->actingAs($user)->getJson('/admin/operations/observability/metrics');
        $response->assertOk()->assertJsonPath('http.p95_ms', 100)->assertJsonPath('ddos.status', 'attack')->assertJsonPath('ddos.events', 120)->assertJsonPath('ddos.under_attack_mode', 'enabled')->assertJsonPath('document_storage.operations.upload.total', 4)->assertJsonPath('document_storage.failures', 1)->assertJsonMissingPath('database.host');
        $this->actingAs($user)->get('/admin/operations/observability')->assertOk()->assertSee('Enterprise Observability')->assertSee('Cloudflare DDoS')->assertSee('Production runtime verification')->assertSee('REQUIRES VPS VERIFICATION');
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('DB_PASSWORD', $content);
        self::assertStringNotContainsString('Authorization', $content);
        self::assertStringNotContainsString('test-token', $content);
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://api.cloudflare.com/client/v4/zones/test-zone/settings/security_level'
            && $request['value'] === 'under_attack');
    }

    public function test_ddos_monitor_is_honestly_unconfigured_without_credentials(): void
    {
        config(['operations.cloudflare_ddos.api_token' => null, 'operations.cloudflare_ddos.zone_id' => null]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->getJson('/admin/operations/observability/metrics')
            ->assertOk()
            ->assertJsonPath('ddos.status', 'unconfigured')
            ->assertJsonPath('ddos.configured', false);

        Http::assertNothingSent();
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
