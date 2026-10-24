<?php

namespace Tests\Unit\Accessibility;

use App\Support\Navigation\AdminHelpRegistry;
use App\Support\Navigation\AdminNavigation;
use App\Support\Navigation\ProcessFlowRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminHelpRegistryTest extends TestCase
{
    public function test_every_primary_navigation_destination_has_a_specific_structured_guide(): void
    {
        $registry = new AdminHelpRegistry;
        $items = array_merge(...array_column((new AdminNavigation)->definitions(41), 'items'));

        self::assertNotEmpty($items);
        self::assertCount(count($items), $registry->definitions(41));

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

    public function test_owned_process_flow_marks_only_the_path_to_current_node_active(): void
    {
        $flow = (new AdminHelpRegistry)->resolve('admin.sales.index', 7)['menuFlow'];
        $positions = array_column($flow, 'position', 'key');

        self::assertSame('upstream', $positions['sales-order']);
        self::assertSame('upstream', $positions['fulfillment']);
        self::assertSame('current', $positions['sales-receivable']);
        self::assertSame('future', $positions['collection']);
        self::assertSame('future', $positions['treasury']);
        self::assertCount(1, array_filter($flow, static fn (array $node): bool => $node['current']));
    }

    public function test_owned_process_flow_only_links_to_registered_navigation_routes(): void
    {
        $flow = (new AdminHelpRegistry)->resolve('admin.procurement.service-acceptances', 7)['menuFlow'];

        foreach ($flow as $node) {
            if ($node['route'] !== null) {
                self::assertTrue(Route::has($node['route']), 'Missing flow route '.$node['route']);
                self::assertNotSame('', $node['permission']);
            }
        }

        self::assertSame('admin.procurement.requests', collect($flow)->firstWhere('key', 'purchase-requests')['route']);
    }

    public function test_every_sidebar_destination_has_one_valid_owner_flow_definition(): void
    {
        $items = array_merge(...array_column((new AdminNavigation)->definitions(7), 'items'));
        $registry = new ProcessFlowRegistry;
        $definitions = $registry->all();

        self::assertCount(143, $items);
        self::assertCount(143, $definitions);
        self::assertEqualsCanonicalizing(array_column($items, 'key'), array_keys($definitions));
        self::assertFalse(method_exists(AdminHelpRegistry::class, 'menuFlowKeys'));

        foreach ($definitions as $menuKey => $definition) {
            self::assertNotSame('', $definition['id'] ?? '', $menuKey.' missing id');
            self::assertContains($definition['category'] ?? null, ['MASTER_DATA', 'CONFIGURATION', 'BUSINESS_PROCESS', 'CONTROL', 'READ_MODEL', 'REPORT', 'EXTERNAL', 'SYSTEM']);
            self::assertMatchesRegularExpression('/^Modules\/[A-Za-z]+\/resources\/process-flows\.php$/', (string) ($definition['file'] ?? ''));
            self::assertNotSame('', $definition['owner'] ?? '', $menuKey.' missing owner');

            $nodes = $definition['nodes'] ?? [];
            $nodeKeys = array_column($nodes, 'key');
            self::assertNotEmpty($nodes, $menuKey.' has no nodes');
            self::assertSame($nodeKeys, array_values(array_unique($nodeKeys)), $menuKey.' has duplicate node keys');
            self::assertContains($definition['current'], $nodeKeys, $menuKey.' current node missing');
            self::assertCount(max(0, count($nodeKeys) - 1), $definition['edges'] ?? [], $menuKey.' graph is not a single acyclic path');

            foreach ($definition['edges'] ?? [] as $edge) {
                self::assertContains($edge['from'], $nodeKeys, $menuKey.' edge source missing');
                self::assertContains($edge['to'], $nodeKeys, $menuKey.' edge target missing');
                self::assertNotSame($edge['from'], $edge['to'], $menuKey.' has self edge');
            }
        }
    }

    public function test_all_resolved_flow_nodes_have_valid_route_and_permission_contracts(): void
    {
        $items = array_merge(...array_column((new AdminNavigation)->definitions(7), 'items'));
        $navigation = array_column($items, null, 'key');
        $registry = new ProcessFlowRegistry;

        foreach ($items as $item) {
            $flow = $registry->resolve($item['key'], $navigation);
            self::assertNotNull($flow, $item['key'].' did not resolve');
            self::assertCount(1, array_filter($flow, static fn (array $node): bool => $node['current']));
            foreach ($flow as $node) {
                self::assertNotSame('', $node['permission'], $item['key'].' / '.$node['key'].' missing permission');
                if ($node['route'] !== null) {
                    self::assertTrue(Route::has($node['route']), 'Missing route '.$node['route']);
                }
            }
        }
    }

    public function test_help_and_visible_strip_share_the_same_permission_filtered_menu_flow(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        self::assertIsString($layout);
        self::assertSame(2, substr_count($layout, '$adminHelp[\'menuFlow\']'));
        self::assertSame(2, substr_count($layout, 'auth()->user()?->can($node[\'permission\'])'));
        self::assertStringContainsString('<x-admin-process-flow :nodes="$pageProcessFlow" />', $layout);
        self::assertStringContainsString('@foreach($visibleFlow as $node)', $layout);
    }

    public function test_process_flow_documentation_has_a_lookup_row_for_every_destination(): void
    {
        $documentation = file_get_contents(base_path('docs/erp-process-flow-convention.md'));
        self::assertIsString($documentation);
        self::assertSame(128, preg_match_all('/^\| .* \| `[^`]+` \| `(?:MASTER_DATA|CONFIGURATION|BUSINESS_PROCESS|CONTROL|READ_MODEL|REPORT|EXTERNAL|SYSTEM)` \| `[^`]+` \| `Modules\/[^`]+\/resources\/process-flows\.php` \| `[^`]+` \| `[^`]+` \|$/m', $documentation));
    }
}
