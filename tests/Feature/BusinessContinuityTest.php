<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class BusinessContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_continuity_dashboard_requires_authentication_verification_and_permission(): void
    {
        $this->get('/admin/operations/continuity')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/continuity')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $this->actingAs($user)->get('/admin/operations/continuity')->assertOk()->assertSee('Business Continuity')->assertSee('Service recovery contract')->assertDontSee('DB_PASSWORD');
    }

    public function test_dr_command_refuses_normal_testing_database(): void
    {
        self::assertSame(1, Artisan::call('operations:dr-check', ['--exercise-id' => 'unsafe']));
        self::assertStringContainsString('isolated schema_admin_testing_*dr', Artisan::output());
    }
}
