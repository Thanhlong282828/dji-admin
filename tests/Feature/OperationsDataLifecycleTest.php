<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationsDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_enforces_auth_verified_permission_and_safe_read_only_payload(): void
    {
        $this->get('/admin/operations/data-lifecycle')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/data-lifecycle')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/data-lifecycle')
            ->assertOk()
            ->assertSee('Enterprise Data Lifecycle')
            ->assertSee('Destructive switch')
            ->assertSee('DISABLED')
            ->assertDontSee('Archive now')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('LocalAdmin123!');
    }

    public function test_read_only_lifecycle_command_emits_safe_json(): void
    {
        self::assertSame(0, Artisan::call('operations:data-lifecycle-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringContainsString('"destructive_disposal_enabled": false', $output);
        self::assertStringContainsString('"policy_count": 13', $output);
        self::assertStringNotContainsString('storage_path', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
