<?php

namespace Tests\Unit\Architecture;

use Illuminate\Routing\Route;
use Tests\TestCase;

final class AdminRouteSecurityGovernanceTest extends TestCase
{
    /**
     * Routes whose action-specific Gate/Policy check is performed inside the controller.
     *
     * @var list<string>
     */
    private const POLICY_PROTECTED_ROUTES = [
        'admin.attendance-adjustments.transition',
        'admin.attendance.export',
        'admin.attendance.transition',
        'admin.career.action',
        'admin.leave-balances.export',
        'admin.leave.export',
        'admin.leave-management.execute',
        'admin.leave-management.update',
        'admin.leave-attachments.upload',
        'admin.leave-attachments.update',
        'admin.leave.transition',
        'admin.overtime.export',
        'admin.overtime.execute',
        'admin.overtime.update',
        'admin.payroll-input.index',
        'admin.payroll-input.export',
        'admin.recruitment-requisitions.transition',
        'admin.talent.performance.action',
        'admin.workforce.export',
        'admin.workforce.calendar-days.update',
        'admin.workforce.shift-breaks.create',
        'admin.workforce.shift-breaks.update',
        'admin.workforce.save',
        'admin.workforce.update',
        'admin.workforce.destroy',
        'admin.workforce.management',
    ];

    public function test_every_non_fixture_admin_route_has_auth_verified_and_permission_or_documented_policy(): void
    {
        /** @var Route $route */
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin/') || str_contains($route->uri(), '/_fixtures/')) {
                continue;
            }

            $middleware = $route->middleware();
            $name = (string) $route->getName();
            self::assertContains('auth', $middleware, $name.' must require authentication');
            self::assertContains('verified', $middleware, $name.' must require a verified user');

            $hasPermission = count(array_filter(
                $middleware,
                static fn (string $item): bool => str_starts_with($item, 'permission:'),
            )) > 0;
            self::assertTrue($hasPermission || in_array($name, self::POLICY_PROTECTED_ROUTES, true), $name.' has no permission boundary');
        }
    }

    public function test_fixture_login_routes_are_not_registered_in_production(): void
    {
        if (! app()->environment('production')) {
            $this->markTestSkipped('Production-only route registration assertion.');
        }

        foreach (['audit.fixture.login', 'iam.fixture.login', 'integration.fixture.login', 'notification.fixture.login'] as $name) {
            self::assertNull(app('router')->getRoutes()->getByName($name));
        }
    }
}
