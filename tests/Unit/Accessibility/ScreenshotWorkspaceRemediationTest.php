<?php

namespace Tests\Unit\Accessibility;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ScreenshotWorkspaceRemediationTest extends TestCase
{
    #[Test]
    public function screenshot_workspaces_use_bounded_enterprise_structures(): void
    {
        $expectations = [
            'resources/views/admin/analytics/enterprise.blade.php' => ['enterprise-command-grid', 'erp-workspace', 'erp-money', 'seriesDestinations', 'erp-source-link'],
            'Modules/Recruitment/resources/views/dashboard/index.blade.php' => ['erp-kpi-grid', 'admin.applications.pipeline', 'erp-rank-row--link', 'NO_OPERATING_DATA'],
            'Modules/Onboarding/resources/views/dashboard/index.blade.php' => ['erp-kpi-grid', 'admin.onboarding-cases.index', 'erp-rank-row--link', 'NO_OPERATING_DATA'],
            'Modules/Performance/resources/views/dashboard.blade.php' => ['data-employee-performance-search', 'array_slice', 'NO_OPERATING_DATA'],
            'Modules/Performance/resources/views/career/dashboard.blade.php' => ['erp-metric-grid', 'NO_OPERATING_DATA'],
            'resources/views/admin/branch-commerce/dashboard.blade.php' => ['erp-workspace', 'VALID_ZERO', 'CURRENT MONTH'],
            'resources/views/admin/finance/financial-statements.blade.php' => ['erp-panel-grid', 'erp-money', 'PARTIAL'],
            'resources/views/admin/finance/overview.blade.php' => ['erp-metric-grid', 'NOT CLOSING CASH', 'PARTIAL'],
            'resources/views/admin/finance/treasury.blade.php' => ['treasury-workspace', 'admin-form-grid', 'admin-table-wrap', 'procurementPayableUrl', 'erp-source-card', 'erp-source-link'],
            'resources/views/admin/finance/payroll-reconciliation.blade.php' => ['admin.payroll.runs.show', "@can('payroll-run.view')", 'erp-source-link'],
            'Modules/Maintenance/resources/views/overview.blade.php' => ['admin.maintenance.requests', 'admin.maintenance.work-orders', 'admin.maintenance.plans'],
        ];

        foreach ($expectations as $path => $needles) {
            $source = file_get_contents(base_path($path));
            self::assertIsString($source, $path);
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $source, $path.' lacks '.$needle);
            }
        }
    }

    #[Test]
    public function shared_css_bounds_metrics_money_tables_and_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        self::assertIsString($css);
        self::assertStringContainsString('.erp-workspace', $css);
        self::assertStringContainsString('.erp-metric-grid', $css);
        self::assertStringContainsString('.erp-rank-list', $css);
        self::assertStringContainsString('.erp-rank-row--link', $css);
        self::assertStringContainsString('.treasury-workspace .admin-form-grid', $css);
        self::assertStringContainsString('.erp-money', $css);
        self::assertStringContainsString('.erp-source-link', $css);
        self::assertStringContainsString('.erp-source-card', $css);
        self::assertStringContainsString('cursor:pointer', $css);
        self::assertStringContainsString('overflow-x:auto', $css);
        self::assertStringContainsString('prefers-reduced-motion:reduce', $css);
    }
}
