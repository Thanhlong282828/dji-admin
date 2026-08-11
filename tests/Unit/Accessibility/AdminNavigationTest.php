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
        self::assertCount(9, $groups);
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
        self::assertSame(['Reliability', 'Observability', 'Performance', 'Releases', 'Runtime Configuration', 'Secrets', 'Data Protection', 'Business Continuity', 'Security Assurance', 'API Documentation'], array_column($operations['items'], 'label'));
    }

    public function test_fully_permitted_user_receives_nine_groups_and_ten_operations_children(): void
    {
        $navigation = new AdminNavigation;
        $user = User::factory()->create();
        $permissions = array_unique(array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 'permission'), $navigation->definitions())));
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $groups = $navigation->for($user, Request::create('/dashboard'), 1);
        self::assertCount(9, $groups);
        $operations = array_values(array_filter($groups, static fn (array $group): bool => $group['key'] === 'operations'))[0];
        self::assertCount(10, $operations['items']);
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
