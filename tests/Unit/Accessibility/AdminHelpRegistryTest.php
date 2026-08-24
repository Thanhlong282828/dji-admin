<?php

namespace Tests\Unit\Accessibility;

use App\Support\Navigation\AdminHelpRegistry;
use App\Support\Navigation\AdminNavigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminHelpRegistryTest extends TestCase
{
    public function test_every_primary_navigation_destination_has_a_specific_structured_guide(): void
    {
        $registry = new AdminHelpRegistry;
        $items = array_merge(...array_column((new AdminNavigation)->definitions(41), 'items'));

        self::assertCount(77, $items);
        self::assertCount(77, $registry->definitions(41));

        foreach ($items as $item) {
            $guide = $registry->resolve($item['route'], 41);
            self::assertFalse($guide['fallback'], $item['key'].' resolved to generic fallback');
            self::assertSame($item['key'], $guide['key']);
            self::assertNotSame('', $guide['purpose']);
            foreach (['dataSources', 'mainActions', 'workflowSteps', 'businessRules', 'downstreamEffects', 'sideEffects', 'permissions', 'warnings', 'menuFlow', 'technicalFlow'] as $section) {
                self::assertNotEmpty($guide[$section], $item['key'].' missing '.$section);
            }
            self::assertCount(1, array_filter($guide['menuFlow'], static fn (array $node): bool => $node['current']));
            self::assertContains($item['key'], array_column($guide['menuFlow'], 'key'));
            self::assertContains($item['permission'], $guide['permissions']);
        }
    }

    public function test_business_menu_flows_connect_real_upstream_and_downstream_destinations(): void
    {
        $registry = new AdminHelpRegistry;

        self::assertSame(
            ['organization-units', 'job-positions', 'employees', 'employee-lifecycle', 'attendance', 'payroll-inputs'],
            array_column($registry->resolve('admin.employees.index')['menuFlow'], 'key'),
        );
        self::assertSame(
            ['employees', 'attendance', 'leave', 'overtime', 'payroll-periods', 'payroll-inputs', 'payroll-runs'],
            array_column($registry->resolve('admin.payroll.inputs.index')['menuFlow'], 'key'),
        );
        self::assertSame(
            ['requisitions', 'candidates', 'applications', 'interviews', 'candidate-offers', 'onboarding', 'employees'],
            array_column($registry->resolve('admin.onboarding.dashboard')['menuFlow'], 'key'),
        );
    }

    public function test_fallback_and_status_guide_are_deterministic(): void
    {
        $registry = new AdminHelpRegistry;
        self::assertTrue($registry->resolve('admin.unknown.detail')['fallback']);

        $payroll = $registry->resolve('admin.payroll.runs.show');
        self::assertFalse($payroll['fallback']);
        self::assertNotEmpty($payroll['statusGuide']);
        foreach ($payroll['statusGuide'] as $status) {
            self::assertSame(['status', 'meaning', 'next'], array_keys($status));
        }
    }

    public function test_related_menu_metadata_uses_real_named_routes_and_permissions(): void
    {
        $definitions = (new AdminHelpRegistry)->definitions(7);
        foreach ($definitions as $guide) {
            foreach ($guide['relatedMenus'] as $menu) {
                self::assertTrue(Route::has($menu['route']), 'Missing related route '.$menu['route']);
                self::assertNotSame('', $menu['permission']);
                self::assertNotSame('', $menu['label']);
            }
        }

        $payrollInput = (new AdminHelpRegistry)->resolve('admin.payroll.inputs.index', 7);
        self::assertContains('Nhân viên', array_column($payrollInput['relatedMenus'], 'label'));
        self::assertContains('Payroll Runs', array_column($payrollInput['relatedMenus'], 'label'));
    }
}
