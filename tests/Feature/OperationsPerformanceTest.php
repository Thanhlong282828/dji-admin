<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class OperationsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_performance_dashboard_authorization_and_safe_payload(): void
    {
        $this->get('/admin/operations/performance')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/performance')->assertForbidden();
        $user->givePermissionTo('operations.view');
        $this->actingAs($user)->get('/admin/operations/performance')->assertOk()
            ->assertSee('Enterprise Performance')->assertSee('Employee')
            ->assertDontSee('DB_PASSWORD')->assertDontSee('bank_account');
    }

    public function test_read_only_audit_command_emits_checksum(): void
    {
        self::assertSame(0, Artisan::call('operations:performance-audit', ['--json' => true]));
        self::assertStringContainsString('"checksum"', Artisan::output());
    }
}
