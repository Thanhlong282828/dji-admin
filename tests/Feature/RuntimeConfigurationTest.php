<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class RuntimeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_page_is_authenticated_verified_and_permission_protected(): void
    {
        $this->get('/admin/operations/configuration')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/configuration')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/configuration')->assertOk()->assertSee('Runtime Configuration')->assertSee('integration.outbound_delivery')->assertDontSee('DB_PASSWORD')->assertDontSee('APP_KEY');
    }

    public function test_config_audit_is_read_only_and_secret_free(): void
    {
        self::assertSame(0, Artisan::call('operations:config-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('snapshot_checksum', $output);
        self::assertStringContainsString('"drift_count": 0', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
