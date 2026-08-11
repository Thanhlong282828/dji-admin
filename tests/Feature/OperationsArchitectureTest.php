<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class OperationsArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_architecture_dashboard_is_authorized_and_safe(): void
    {
        $this->get('/admin/operations/architecture')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/architecture')->assertForbidden();
        $user->givePermissionTo('operations.view');
        $this->actingAs($user)->get('/admin/operations/architecture')->assertOk()
            ->assertSee('Enterprise Architecture')
            ->assertSee('Undeclared dependencies')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('bank_account');
    }

    public function test_architecture_command_is_read_only_and_emits_safe_json(): void
    {
        self::assertSame(0, Artisan::call('operations:architecture-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringContainsString('"undeclared_edges": []', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
