<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class DataProtectionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication_verification_and_permission(): void
    {
        $this->get('/admin/operations/data-protection')->assertRedirect('/login');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/data-protection')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $this->actingAs($user)->get('/admin/operations/data-protection')->assertOk()->assertSee('Enterprise Data Protection')->assertSee('bank_account')->assertDontSee('DB_PASSWORD')->assertDontSee('APP_KEY');
    }

    public function test_read_only_audit_command_outputs_metadata_only(): void
    {
        self::assertSame(0, Artisan::call('operations:data-protection-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('classification_counts', $output);
        self::assertStringContainsString('checksum', $output);
        self::assertStringNotContainsString('LocalAdmin123!', $output);
        self::assertStringNotContainsString('DB_PASSWORD', $output);
    }
}
