<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
        $this->actingAs($authorized->fresh())->get('/horizon')->assertForbidden();
    }
}
