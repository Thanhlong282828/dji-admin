<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AccessibilityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

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
            ->assertSee('schema.admin.navigation.open-groups', false)
            ->assertSee('schema.admin.navigation.scroll-top', false)
            ->assertSee('adminNavReady', false)
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
        $this->actingAs($user)->get('/admin/operations/security-assurance')->assertOk()->assertSee('Vận hành nền tảng')->assertSee('Security Assurance')->assertDontSee('API Documentation');
        $this->actingAs($user)->get('/admin/operations/data-lifecycle')->assertOk()->assertSee('Data Lifecycle');

        $user->givePermissionTo(Permission::findOrCreate('api.documentation.view', 'web'));
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('API Documentation');
    }

    public function test_api_documentation_permission_can_render_operations_with_only_its_permitted_child(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo([
            Permission::findOrCreate('dashboard.view', 'web'),
            Permission::findOrCreate('api.documentation.view', 'web'),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-nav-group="operations"', false)
            ->assertSee('API Documentation')
            ->assertDontSee('Security Assurance');
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

    public function test_procurement_sidebar_and_subnavigation_only_render_authorized_destination(): void
    {
        $company = (int) DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'code' => 'NAV', 'name' => 'Navigation Company',
            'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND',
            'currency' => 'VND', 'status' => 'active', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('service-acceptance.view', 'web'));
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get(route('admin.procurement.service-acceptances', ['company_id' => $company]));
        $response->assertOk()
            ->assertSee('data-nav-group="procurement"', false)
            ->assertSee(route('admin.procurement.service-acceptances', ['company_id' => $company]), false)
            ->assertDontSee(route('admin.procurement.receipts', ['company_id' => $company]), false)
            ->assertDontSee(route('admin.procurement.payables', ['company_id' => $company]), false)
            ->assertDontSee('service-reversals', false);

        $this->actingAs($user)->get(route('admin.procurement.receipts', ['company_id' => $company]))->assertForbidden();
    }
}
