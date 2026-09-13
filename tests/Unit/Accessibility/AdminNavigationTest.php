<?php

namespace Tests\Unit\Accessibility;

use App\Models\User;
use App\Support\Accessibility\ColorContrastCalculator;
use App\Support\Navigation\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_has_unique_group_item_and_route_keys_with_permission_metadata(): void
    {
        $groups = (new AdminNavigation)->definitions();
        self::assertCount(18, $groups);
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
        self::assertSame(['Reliability', 'Observability', 'Performance', 'Architecture', 'Test Quality', 'API Contracts', 'Releases', 'Runtime Configuration', 'Secrets', 'Data Protection', 'Data Lifecycle', 'Data Quality', 'Business Continuity', 'Security Assurance', 'Go-Live Readiness', 'API Documentation'], array_column($operations['items'], 'label'));
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

    public function test_fully_permitted_user_receives_eighteen_groups_and_sixteen_operations_children(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        $permissions = array_unique(array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'permission'), $navigation->definitions())));
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        self::assertCount(18, $groups);
        $operations = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'operations'))[0];
        self::assertCount(16, $operations['items']);
        self::assertContains('admin.operations.data-quality', array_column($operations['items'], 'route'));
    }

    public function test_procurement_children_follow_lifecycle_order_and_are_permission_filtered(): void
    {
        $navigation = new AdminNavigation;
        $definitions = $navigation->definitions();
        $procurement = array_values(array_filter($definitions, static fn (array $group): bool => $group['key'] === 'procurement'))[0];

        self::assertSame(
            ['procurement-vendors', 'purchase-requests', 'purchase-orders', 'goods-receipts', 'service-acceptances', 'vendor-bills', 'procurement-reports'],
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
            ['overview', 'organization', 'people', 'workforce', 'talent', 'payroll', 'tax-statutory', 'seller', 'pos', 'procurement', 'supply-chain', 'finance', 'asset-maintenance', 'projects', 'automation', 'analytics-planning', 'access', 'operations'],
            array_column((new AdminNavigation)->definitions(), 'key'),
        );
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
