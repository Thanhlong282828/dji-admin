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
use Tests\TestCase;

final class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_has_unique_group_item_and_route_keys_with_permission_metadata(): void
    {
        $groups = (new AdminNavigation)->definitions();
        self::assertCount(10, $groups);
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
        self::assertSame(['Reliability', 'Observability', 'Performance', 'Architecture', 'Test Quality', 'API Contracts', 'Releases', 'Runtime Configuration', 'Secrets', 'Data Protection', 'Data Lifecycle', 'Data Quality', 'Business Continuity', 'Security Assurance', 'API Documentation'], array_column($operations['items'], 'label'));
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

    public function test_fully_permitted_user_receives_ten_groups_and_fifteen_operations_children(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        $permissions = array_unique(array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'permission'), $navigation->definitions())));
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        self::assertCount(10, $groups);
        $operations = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'operations'))[0];
        self::assertCount(15, $operations['items']);
        self::assertContains('admin.operations.data-quality', array_column($operations['items'], 'route'));
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
