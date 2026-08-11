<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SecretGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_secrets_page_requires_authentication_verification_and_permission(): void
    {
        $this->get('/admin/operations/secrets')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/secrets')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $appKey = config('app.key');
        self::assertIsString($appKey);
        $this->actingAs($user)->get('/admin/operations/secrets')->assertOk()->assertSee('Secrets Governance')->assertSee('secret://application/app-key')->assertDontSee($appKey)->assertDontSee('DB_PASSWORD');
    }

    public function test_secret_audit_output_is_presence_only(): void
    {
        self::assertSame(0, Artisan::call('operations:secrets-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('secret://application/app-key', $output);
        self::assertStringContainsString('"ready": true', $output);
        $appKey = config('app.key');
        self::assertIsString($appKey);
        self::assertStringNotContainsString($appKey, $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
