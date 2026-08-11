<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Modules\Integration\Infrastructure\Adapters\GenericHttpAdapter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SecurityAssuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication_verification_and_permission(): void
    {
        $this->get('/admin/operations/security-assurance')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/security-assurance')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $this->actingAs($user)->get('/admin/operations/security-assurance')
            ->assertOk()
            ->assertSee('Security Assurance')
            ->assertSee('SEC-SSRF-001')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('APP_KEY');
    }

    public function test_dashboard_escapes_synthetic_xss_content(): void
    {
        $html = view('operations.security-assurance', [
            'summary' => ['status' => 'safe', 'routes' => 1, 'privileged' => 1, 'critical' => 0, 'high' => 0],
            'findings' => [['key' => 'XSS', 'severity' => 'low', 'component' => '<script>window.securityXss=true</script>', 'description' => 'synthetic', 'evidence' => 'escaped', 'status' => 'fixed']],
            'boundaries' => [],
        ])->render();
        self::assertStringNotContainsString('<script>window.securityXss=true</script>', $html);
        self::assertStringContainsString('&lt;script&gt;window.securityXss=true&lt;/script&gt;', $html);
    }

    public function test_assurance_command_is_read_only_and_outputs_safe_summary(): void
    {
        self::assertSame(0, Artisan::call('operations:security-assurance', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('assured_with_residual_risk', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }

    public function test_integration_adapter_rejects_private_target_before_delivery(): void
    {
        $this->expectException(ValidationException::class);
        (new GenericHttpAdapter)->validateConfiguration(['base_url' => 'https://169.254.169.254/latest/meta-data']);
    }
}
