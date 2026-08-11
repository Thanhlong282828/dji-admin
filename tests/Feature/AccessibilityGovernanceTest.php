<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AccessibilityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_has_landmarks_skip_link_and_accessible_navigation_controls(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));

        $response = $this->actingAs($user)->withSession(['success' => 'Synthetic success'])->get('/admin/operations/security-assurance');
        $response->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-label="Điều hướng chính"', false)
            ->assertSee('aria-controls="nav-group-operations"', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('role="status"', false)
            ->assertDontSee('DB_PASSWORD');
    }

    public function test_operations_group_visibility_respects_permission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/admin/operations/security-assurance')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('data-nav-group="operations"', false)->assertDontSee('Security Assurance');
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $this->actingAs($user)->get('/admin/operations/security-assurance')->assertOk()->assertSee('Vận hành nền tảng')->assertSee('Security Assurance');
    }

    public function test_current_operations_route_expands_group_and_marks_active_item(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('operations.view', 'web'));
        $html = $this->actingAs($user)->get('/admin/operations/continuity')->assertOk()->getContent();
        self::assertIsString($html);
        self::assertMatchesRegularExpression('/data-nav-group="operations"[^>]*data-route-active="true"/', $html);
        self::assertMatchesRegularExpression('/class="nav-item active"[^>]*aria-current="page"/', $html);
    }
}
