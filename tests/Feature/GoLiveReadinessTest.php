<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class GoLiveReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_actor_without_permission_is_forbidden(): void
    {
        $this->get('/admin/operations/go-live')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/operations/go-live')->assertForbidden();
    }

    public function test_operations_actor_sees_read_only_redacted_go_live_cockpit(): void
    {
        config()->set('database.connections.mysql.password', 'never-render-this-secret');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/go-live')
            ->assertOk()
            ->assertSee('Go-Live Readiness')
            ->assertSee('PRODUCTION_BLOCKED_EXTERNAL')
            ->assertSee('Trang này không deploy')
            ->assertDontSee('never-render-this-secret');
    }
}
