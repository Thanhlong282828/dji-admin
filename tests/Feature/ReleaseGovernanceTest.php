<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ReleaseGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_dashboard_requires_authentication_and_permission(): void
    {
        $this->get('/admin/operations/releases')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/releases')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $this->actingAs($user)->get('/admin/operations/releases')->assertOk()->assertSee('Enterprise Releases')->assertDontSee('DB_PASSWORD');
    }

    public function test_migration_risk_command_is_read_only_and_reports_inventory(): void
    {
        self::assertSame(0, Artisan::call('operations:migration-risk'));
        self::assertStringContainsString('migrations', Artisan::output());
    }

    public function test_release_check_fails_closed_without_evidence_or_immutable_images(): void
    {
        self::assertSame(1, Artisan::call('operations:release-check', ['--allow-stale-scheduler' => true]));
        self::assertStringContainsString('"status": "fail"', Artisan::output());
    }
}
