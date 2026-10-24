<?php

namespace Tests\Unit\Accessibility;

use App\Models\User;
use App\Support\Accessibility\ColorContrastCalculator;
use App\Support\Navigation\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_navigation_has_unique_group_item_and_route_keys_with_permission_metadata(): void
    {
        $groups = (new AdminNavigation)->definitions();
        self::assertCount(20, $groups);
        self::assertSame(count($groups), count(array_unique(array_column($groups, 'key'))));
        $items = array_merge(...array_column($groups, 'items'));
        self::assertSame(count($items), count(array_unique(array_column($items, 'key'))));
        self::assertSame(count($items), count(array_unique(array_column($items, 'route'))));
        foreach ($items as $item) {
            self::assertTrue(Route::has($item['route']), 'Missing menu route: '.$item['route']);
            self::assertNotSame('', $item['permission']);
            self::assertDoesNotMatchRegularExpression('/password|token|secret value|salary|bank|private path/i', $item['label']);
        }
    }

    public function test_operations_group_contains_all_governed_surfaces(): void
    {
        $groups = (new AdminNavigation)->definitions();
        $operations = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'operations'))[0];
        self::assertSame(['Integrations', 'Reliability', 'Observability', 'Performance', 'Architecture', 'Test Quality', 'API Contracts', 'Releases', 'Runtime Configuration', 'Secrets', 'Data Protection', 'Data Lifecycle', 'Data Quality', 'Business Continuity', 'Security Assurance', 'Go-Live Readiness', 'API Documentation'], array_column($operations['items'], 'label'));
    }

    public function test_every_sidebar_permission_matches_the_landing_route_middleware(): void
    {
        $items = array_merge(...array_column((new AdminNavigation)->definitions(), 'items'));

        foreach ($items as $item) {
            $route = Route::getRoutes()->getByName($item['route']);
            self::assertNotNull($route, 'Missing menu route: '.$item['route']);
            $middleware = $route->gatherMiddleware();
            $dynamicControllerBoundary = $item['route'] === 'admin.workforce.management'
                && $item['permission'] === 'shift.view';
            self::assertTrue(
                $dynamicControllerBoundary || count(array_filter($middleware, static fn (mixed $name): bool => is_string($name) && str_contains($name, ':'.$item['permission']))) > 0,
                'Sidebar permission mismatch: '.$item['route'],
            );
        }
    }

    public function test_previously_unreachable_primary_work_centers_are_present(): void
    {
        $items = array_merge(...array_column((new AdminNavigation)->definitions(), 'items'));

        foreach ([
            'leave-management', 'overtime', 'requisitions', 'candidates', 'applications', 'interviews',
            'candidate-offers', 'payroll-dashboard', 'tax-administration', 'tax-policies',
            'insurance-administration', 'insurance-policies', 'accounting-configuration',
            'erp-configuration', 'workflow-definitions', 'workflow-tasks', 'notification-templates',
            'notification-deliveries', 'access-requests', 'roles-permissions', 'segregation-of-duties',
        ] as $key) {
            self::assertContains($key, array_column($items, 'key'));
        }
    }

    public function test_fully_permitted_user_receives_twenty_groups_and_seventeen_operations_children(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        $permissions = array_unique(array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'permission'), $navigation->definitions())));
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        self::assertCount(20, $groups);
        $operations = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'operations'))[0];
        self::assertCount(17, $operations['items']);
        self::assertContains('admin.operations.data-quality', array_column($operations['items'], 'route'));
    }

    public function test_context_bound_destinations_are_hidden_until_their_source_context_exists(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        foreach (['employee.portal.view', 'branch-commerce.view', 'organization.tenant-all'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        $keys = array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'key'), $groups));
        self::assertNotContains('employee-portal', $keys);
        self::assertNotContains('branch-warehouse', $keys);

        DB::table('companies')->insert([
            'id' => 1, 'uuid' => fake()->uuid(), 'code' => 'NAV-CONTEXT', 'name' => 'Navigation Context',
            'country_code' => 'VN', 'currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employees')->insert([
            'uuid' => fake()->uuid(), 'user_id' => $user->id, 'company_id' => 1,
            'employee_code' => 'NAV-EMPLOYEE', 'full_name' => 'Navigation Employee',
            'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active',
            'employment_type' => 'full_time', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'company_id' => 1, 'code' => 'NAV-BRANCH', 'name' => 'Navigation Branch',
            'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        $keys = array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'key'), $groups));
        self::assertContains('employee-portal', $keys);
        self::assertContains('branch-warehouse', $keys);
    }

    public function test_procurement_children_follow_lifecycle_order_and_are_permission_filtered(): void
    {
        $navigation = new AdminNavigation;
        $definitions = $navigation->definitions();
        $procurement = array_values(array_filter($definitions, static fn (array $group): bool => $group['key'] === 'procurement'))[0];

        self::assertSame(
            ['procurement-vendors', 'purchase-requests', 'purchase-orders', 'goods-receipts', 'goods-returns', 'supplier-credits', 'service-acceptances', 'vendor-bills', 'procurement-reports'],
            array_column($procurement['items'], 'key'),
        );
        self::assertNotContains('service-reversals', array_column($procurement['items'], 'key'));

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('service-acceptance.view', 'web'));
        $request = Request::create('/admin/procurement/service-acceptances');
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        $groups = $navigation->for($user, $request, 1);
        $visible = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'procurement'))[0];

        self::assertSame(['service-acceptances'], array_column($visible['items'], 'key'));
        self::assertTrue($visible['items'][0]['active']);
    }

    public function test_top_level_groups_follow_stable_erp_business_order(): void
    {
        self::assertSame(
            ['overview', 'people', 'workforce', 'talent', 'payroll', 'crm', 'supply-chain', 'inventory', 'seller', 'pos', 'procurement', 'finance', 'tax-statutory', 'asset-maintenance', 'documents', 'analytics-planning', 'content', 'access', 'automation', 'operations'],
            array_column((new AdminNavigation)->definitions(), 'key'),
        );
    }

    public function test_groups_use_canonical_erp_domain_labels_without_empty_domains(): void
    {
        $groups = (new AdminNavigation)->definitions();
        $domains = array_map(static fn (array $group): string => trim(explode('·', $group['label'], 2)[0]), $groups);

        self::assertSame(
            ['ERP', 'HRM', 'HRM', 'HRM', 'HRM', 'CRM', 'SCM', 'WMS', 'OMS', 'POS', 'PMS', 'FMS', 'FMS', 'EAM', 'DMS', 'BI', 'CMS', 'IAM', 'SYS', 'ITSM'],
            $domains,
        );
        foreach ($groups as $group) {
            self::assertNotSame([], $group['items'], 'Canonical navigation groups must not be empty: '.$group['key']);
        }
    }

    public function test_contextual_capabilities_do_not_create_duplicate_sidebar_destinations(): void
    {
        $items = array_merge(...array_column((new AdminNavigation)->definitions(), 'items'));
        $keys = array_column($items, 'key');

        self::assertContains('procurement-vendors', $keys);
        self::assertContains('procurement-reports', $keys);
        self::assertContains('treasury', $keys);
        self::assertSame(1, count(array_filter($keys, static fn (string $key): bool => $key === 'approval-inbox')));
        foreach (['vendor-performance', 'procurement-exceptions', 'service-reversals', 'ap-aging', 'payment-planning', 'cash-outflow-forecast'] as $contextual) {
            self::assertNotContains($contextual, $keys);
        }
    }

    public function test_crm_routes_are_permission_filtered_and_keep_company_scope(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('crm.view', 'web'));

        $request = Request::create('/admin/crm/leads');
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        $groups = $navigation->for($user, $request, 77);
        $crm = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'crm'))[0];

        self::assertSame(['crm-pipeline', 'crm-leads', 'crm-opportunities'], array_column($crm['items'], 'key'));
        self::assertSame([['company_id' => 77], ['company_id' => 77], ['company_id' => 77]], array_column($crm['items'], 'parameters'));
        self::assertTrue($crm['items'][1]['active']);
        self::assertTrue($crm['items'][0]['process_help']);
    }

    public function test_super_admin_receives_registered_crm_permission_without_manual_assignment(): void
    {
        Permission::findOrCreate('crm.view', 'web');
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));

        self::assertTrue($user->can('crm.view'));
        self::assertFalse($user->can('unregistered-sensitive-action'));

        $groups = (new AdminNavigation)->for($user, Request::create('/dashboard'), 1);
        $crm = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'crm'))[0];

        self::assertSame('CRM · Lead & Opportunity', $crm['label']);
        self::assertSame(['crm-pipeline', 'crm-leads', 'crm-opportunities'], array_column($crm['items'], 'key'));
    }

    public function test_role_inherited_permission_exposes_only_the_canonical_treasury_workspace(): void
    {
        $permission = Permission::findOrCreate('treasury.view', 'web');
        $role = Role::findOrCreate('Navigation Treasury Reader', 'web');
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);
        $groups = (new AdminNavigation)->for($user, Request::create('/admin/finance/treasury'), 77);
        $finance = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'finance'))[0];

        self::assertSame(['treasury'], array_column($finance['items'], 'key'));
        self::assertSame(['company_id' => 77], $finance['items'][0]['parameters']);
        self::assertNotContains('operations', array_column($groups, 'key'));
    }

    public function test_group_without_any_permitted_child_is_absent(): void
    {
        $groups = (new AdminNavigation)->for(User::factory()->create(), Request::create('/dashboard'), 1);
        self::assertSame([], $groups);
    }

    public function test_sidebar_layout_explains_when_user_has_no_visible_capability(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        self::assertIsString($layout);
        self::assertStringContainsString('@forelse($navigationGroups as $group)', $layout);
        self::assertStringContainsString('Chưa có chức năng được cấp quyền', $layout);
        self::assertStringContainsString('groups.at(0)', $layout);
    }

    public function test_operations_and_unauthorized_children_are_filtered_server_side(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));
        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        self::assertNotContains('operations', array_column($groups, 'key'));

        $user->givePermissionTo(Permission::findOrCreate('api.documentation.view', 'web'));
        $groups = $navigation->for($user, Request::create('/api/documentation'), 1);
        $operations = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'operations'))[0];
        self::assertSame(['api-documentation'], array_column($operations['items'], 'key'));

        $user->givePermissionTo(Permission::findOrCreate('payroll-period.view', 'web'));
        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        $payroll = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'payroll'))[0];
        self::assertSame(['payroll-periods', 'payroll-inputs'], array_column($payroll['items'], 'key'));
        self::assertNotContains('government', array_column($payroll['items'], 'key'));
    }

    public function test_black_gold_palette_meets_normal_text_contrast_target(): void
    {
        $calculator = new ColorContrastCalculator;
        self::assertGreaterThanOrEqual(4.5, $calculator->ratio('#d7b45a', '#080808'));
        self::assertGreaterThanOrEqual(4.5, $calculator->ratio('#f5f1e6', '#111111'));
        $this->expectException(InvalidArgumentException::class);
        $calculator->ratio('gold', '#080808');
    }
}
