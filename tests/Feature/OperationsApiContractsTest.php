<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationsApiContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_enforces_auth_verified_permission_and_safe_payload(): void
    {
        $this->get('/admin/operations/api-contracts')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/api-contracts')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/api-contracts')
            ->assertOk()
            ->assertSee('Enterprise API Contracts')
            ->assertSee('passport-authorization-code-pkce')
            ->assertSee('API Contracts')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('LocalAdmin123!');
    }

    public function test_read_only_contract_command_emits_valid_safe_json(): void
    {
        self::assertSame(0, Artisan::call('operations:api-contract-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringContainsString('"status": "pass"', $output);
        self::assertStringContainsString('"actual_operations": 30', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
