<?php

namespace Tests\Unit\Accessibility;

use Tests\TestCase;

final class ErpDesignSystemTest extends TestCase
{
    public function test_semantic_tokens_components_and_accessibility_contract_exist(): void
    {
        $css = $this->contents('resources/css/app.css');
        self::assertStringContainsString('--erp-accent-gradient', $css);
        self::assertStringContainsString('--erp-success:', $css);
        self::assertStringContainsString('--erp-danger:', $css);
        self::assertStringContainsString('.erp-page-hero', $css);
        self::assertStringContainsString('.erp-kpi', $css);
        self::assertStringContainsString('.erp-status--rejected', $css);
        self::assertStringContainsString('.button.danger', $css);
        self::assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);

        self::assertFileExists(resource_path('views/components/erp/page-header.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/kpi-card.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/status-badge.blade.php'));

        $layout = $this->contents('resources/views/layouts/admin.blade.php');
        self::assertStringContainsString('aria-current="page"', $layout);
        self::assertStringContainsString('aria-expanded=', $layout);
        self::assertStringContainsString('data-sidebar-open', $layout);
        self::assertStringContainsString('erp-context-chip', $layout);
        self::assertStringContainsString('class="sidebar-footer"', $layout);
        self::assertStringContainsString('class="sidebar-logout"', $layout);
        self::assertStringContainsString('method="POST"', $layout);
        self::assertStringContainsString("action=\"{{ route('logout') }}\"", $layout);
        self::assertStringContainsString('@csrf', $layout);
        self::assertStringContainsString('@auth', $layout);
        self::assertStringContainsString('x-bind:disabled="submitting"', $layout);

        self::assertStringContainsString('.sidebar-footer{flex:0 0 auto;margin-top:auto}', $css);
        self::assertStringContainsString('background:#EAB308', $css);
        self::assertStringContainsString('.sidebar-logout button:focus-visible', $css);
        self::assertStringContainsString('.sidebar-logout button:disabled', $css);
    }

    public function test_dms_uses_the_shared_page_header_and_status_component(): void
    {
        $index = $this->contents('Modules/Document/resources/views/index.blade.php');
        $show = $this->contents('Modules/Document/resources/views/show.blade.php');
        self::assertStringContainsString('<x-erp.page-header', $index);
        self::assertStringContainsString('<x-erp.page-header', $show);
        self::assertStringContainsString('<x-erp.status-badge', $show);
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(base_path($path));
        self::assertIsString($contents);

        return $contents;
    }
}
