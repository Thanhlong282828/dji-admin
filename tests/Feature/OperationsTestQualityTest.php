<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationsTestQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_enforces_auth_verified_permission_and_safe_payload(): void
    {
        $this->get('/admin/operations/test-quality')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/test-quality')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/test-quality')
            ->assertOk()
            ->assertSee('Enterprise Test Quality')
            ->assertSee('Critical invariant coverage')
            ->assertSee('Test Quality')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('LocalAdmin123!');
    }

    public function test_read_only_quality_command_emits_valid_safe_json(): void
    {
        self::assertSame(0, Artisan::call('operations:test-quality', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringContainsString('"critical_gaps": 0', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
