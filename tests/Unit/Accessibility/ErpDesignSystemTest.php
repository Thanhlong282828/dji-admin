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
        self::assertFileExists(resource_path('views/components/erp/button.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/dashboard.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/dashboard-section.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/widget.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/data-table.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/filter-toolbar.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/row-actions.blade.php'));
        self::assertFileExists(resource_path('views/components/erp/empty-state.blade.php'));
        self::assertStringContainsString('erp-button--primary', $this->contents('resources/views/components/primary-button.blade.php'));
        self::assertStringContainsString('erp-button--secondary', $this->contents('resources/views/components/secondary-button.blade.php'));
        self::assertStringContainsString('erp-button--danger', $this->contents('resources/views/components/danger-button.blade.php'));

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
        self::assertStringContainsString('data-sidebar-collapse', $layout);
        self::assertStringContainsString('data-ui-scale-down', $layout);
        self::assertStringContainsString('data-ui-scale-reset', $layout);
        self::assertStringContainsString('data-ui-scale-up', $layout);
        self::assertStringNotContainsString('<header class="admin-page-header">', $layout);
        self::assertStringContainsString('class="admin-page-toolbar"', $layout);

        self::assertStringContainsString('.sidebar-footer{flex:0 0 auto;margin-top:auto}', $css);
        self::assertStringContainsString('linear-gradient(#000000,#000000) padding-box,var(--erp-brand-motion-gradient) border-box', $css);
        self::assertStringContainsString('animation:brand-current-border var(--erp-brand-motion-duration) linear infinite', $css);
        self::assertStringContainsString('.sidebar-logout button>span', $css);
        self::assertStringContainsString('animation:brand-gold-flow var(--erp-brand-motion-duration) linear infinite', $css);
        self::assertStringContainsString('stroke:var(--erp-accent-mid)', $css);
        self::assertStringContainsString('.sidebar-logout button:focus-visible', $css);
        self::assertStringContainsString('.sidebar-logout button:disabled', $css);
        self::assertStringContainsString('@media(prefers-reduced-motion:reduce){.sidebar-logout button{animation:none!important', $css);
        self::assertStringNotContainsString('background:#EAB308', $css);
        self::assertStringNotContainsString('background:#FACC15', $css);
        self::assertStringContainsString('[data-admin-sidebar-collapsed="true"]', $css);
        foreach ([25, 33, 50, 67, 75, 80, 90, 100, 110] as $scale) {
            self::assertStringContainsString(sprintf('[data-admin-ui-scale="%d"]', $scale), $css);
        }
        self::assertStringContainsString('.admin-shell{', $css);
        self::assertStringContainsString('width:100%;', $css);
        self::assertStringContainsString('min-width:0;', $css);
        self::assertStringContainsString('zoom:var(--uix-scale)', $css);
        self::assertStringContainsString('--uix-viewport-height:400dvh', $css);
        self::assertStringContainsString('--uix-viewport-width:400dvw', $css);
        self::assertStringContainsString('min-height:var(--uix-viewport-height)', $css);
        self::assertStringContainsString('height:var(--uix-viewport-height);max-height:var(--uix-viewport-height)', $css);
        self::assertStringContainsString('.admin-sidebar{position:sticky;top:0;', $css);
        self::assertStringContainsString('max-width:min(46rem,calc(var(--uix-viewport-width) - 2rem));max-height:calc(var(--uix-viewport-height) - 2rem);margin:auto', $css);
        self::assertStringNotContainsString('font-size:calc(1rem * var(--uix-scale))', $css);
        self::assertStringContainsString('.admin-shell>main{', $css);
        self::assertStringContainsString('overflow:visible;', $css);
        self::assertStringNotContainsString('overflow-x:clip', $css);
        self::assertStringNotContainsString('.admin-shell{display:block;min-width:20rem', $css);
        self::assertStringNotContainsString('max-width:var(--uix-content-max)', $css);
        self::assertStringContainsString('.erp-button--warning', $css);
        self::assertStringContainsString('.erp-widget-grid--5', $css);
        self::assertStringContainsString('.erp-kpi--loading', $css);
        self::assertStringContainsString('.erp-kpi--empty', $css);
        self::assertStringContainsString('.erp-kpi--error', $css);
        self::assertStringContainsString('@media(max-width:360px)', $css);
        self::assertStringContainsString('.erp-data-table', $css);
        self::assertStringContainsString('.erp-table-scroll:focus-visible', $css);
        self::assertStringContainsString('.erp-filter-toolbar__inner', $css);
        self::assertStringContainsString('.erp-pagination', $css);

        $kpi = $this->contents('resources/views/components/erp/kpi-card.blade.php');
        self::assertStringContainsString("'loading' => 'Đang tải dữ liệu'", $kpi);
        self::assertStringContainsString("'empty' => 'Chưa có dữ liệu nghiệp vụ'", $kpi);
        self::assertStringContainsString("'error' => 'Không thể tải dữ liệu'", $kpi);
        self::assertStringContainsString('aria-busy="true"', $kpi);
    }

    public function test_real_dashboards_use_the_shared_enterprise_components(): void
    {
        foreach ([
            'resources/views/admin/seller/dashboard.blade.php',
            'resources/views/admin/branch-commerce/dashboard.blade.php',
            'resources/views/admin/finance/tax-dashboard.blade.php',
            'Modules/Iam/resources/views/dashboard.blade.php',
            'Modules/Workflow/resources/views/dashboard.blade.php',
            'Modules/Maintenance/resources/views/overview.blade.php',
            'Modules/Recruitment/resources/views/dashboard/index.blade.php',
            'Modules/Onboarding/resources/views/dashboard/index.blade.php',
            'Modules/Employee/resources/views/governance/dashboard.blade.php',
            'Modules/Performance/resources/views/dashboard.blade.php',
        ] as $path) {
            $view = $this->contents($path);
            self::assertStringContainsString('<x-erp.dashboard', $view, $path);
            self::assertStringContainsString('<x-erp.dashboard-section', $view, $path);
        }

        $commerce = $this->contents('resources/views/admin/branch-commerce/dashboard.blade.php');
        self::assertStringContainsString('VALID_ZERO', $commerce);
        self::assertStringContainsString('Chưa có phiếu thu đã thanh toán', $commerce);
        self::assertStringContainsString("route('admin.branch-commerce.receipts.show'", $commerce);

        $dashboard = $this->contents('resources/views/admin/dashboard/index.blade.php');
        $css = $this->contents('resources/css/app.css');
        self::assertStringContainsString('executive-command-center__dashboard--without-hero', $dashboard);
        self::assertStringNotContainsString('Trung tâm điều hành doanh nghiệp', $dashboard);
        self::assertStringContainsString('title="Chỉ số điều hành"', $dashboard);
        self::assertStringContainsString('label="Tổng nhân sự"', $dashboard);
        self::assertStringContainsString('erp-kpi-grid--command', $dashboard);
        self::assertStringContainsString('icon="◈"', $dashboard);
        self::assertStringContainsString('class="erp-trend__chart" role="img" tabindex="0"', $dashboard);
        self::assertStringContainsString('.erp-trend__chart:focus-visible', $css);
        self::assertStringContainsString('class="executive-command-center" data-executive-command-center', $dashboard);
        self::assertStringContainsString('class="executive-board"', $dashboard);
        self::assertStringContainsString('data-executive-onboarding-step', $dashboard);
        self::assertStringContainsString('role="dialog" aria-modal="true"', $dashboard);
        self::assertStringContainsString('.executive-board__lanes{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))', $css);
        self::assertStringContainsString('@media(max-width:767px){.executive-command-center', $css);
        self::assertStringContainsString('@media(prefers-reduced-motion:reduce){.executive-command-center::before', $css);

        $dashboardScript = $this->contents('resources/js/admin/overview-dashboard.js');
        self::assertStringNotContainsString("addEventListener('pointermove'", $dashboardScript);
        self::assertStringContainsString('event.key === \'Escape\'', $dashboardScript);
        self::assertStringContainsString('event.key !== \'Tab\'', $dashboardScript);
        self::assertStringContainsString('schema.executive-onboarding.completed', $dashboardScript);
        self::assertStringContainsString('data-command-analytics', $dashboard);
        self::assertStringContainsString('data-command-chart="inventory"', $dashboard);
        self::assertStringContainsString('data-command-chart="procurement"', $dashboard);
        self::assertStringContainsString('data-command-chart="revenue"', $dashboard);
        self::assertStringContainsString('data-erp-launcher-search', $dashboard);
        self::assertStringContainsString('data-erp-launcher-item', $dashboard);
        self::assertStringContainsString('Xem bảng dữ liệu', $dashboard);
        self::assertStringContainsString('MULTI_CURRENCY_BLOCKED', $dashboard);
        self::assertStringContainsString('drawDonut', $dashboardScript);
        self::assertStringContainsString('drawSeries', $dashboardScript);
        self::assertStringContainsString('bootWorkspaceLauncher', $dashboardScript);
        self::assertStringContainsString('bindChartTooltip', $dashboardScript);
        self::assertStringContainsString("['ArrowLeft', 'ArrowRight']", $dashboardScript);
        self::assertStringContainsString('data-command-tooltip="inventory"', $dashboard);
        self::assertStringContainsString('data-command-section-toggle', $dashboard);
        self::assertStringContainsString('bootCommandSections', $dashboardScript);
        self::assertStringContainsString('.command-analytics__grid{display:grid', $css);
        self::assertStringContainsString('.command-analytics__grid{grid-template-columns:repeat(3,minmax(0,1fr))', $css);
        self::assertStringContainsString('.operational-command-section__body[hidden]{display:none}', $css);
        self::assertStringContainsString('@media(max-width:900px){.command-analytics__header', $css);

        $goLive = $this->contents('resources/views/operations/go-live.blade.php');
        self::assertStringContainsString('.golive{--gold:#d7b45a;--panel:#11141a;width:100%;min-width:0;max-width:none', $goLive);
        self::assertStringContainsString('class="golive-table" tabindex="0"', $goLive);
        self::assertStringContainsString('.golive-table:focus-visible', $goLive);
    }

    public function test_priority_business_tables_use_the_shared_enterprise_table_system(): void
    {
        foreach ([
            'Modules/Employee/resources/views/employees/index.blade.php',
            'resources/views/admin/finance/fx-rates.blade.php',
            'resources/views/admin/seller/inventory.blade.php',
            'resources/views/admin/procurement/index.blade.php',
        ] as $path) {
            $view = $this->contents($path);
            self::assertStringContainsString('<x-erp.data-table', $view, $path);
            self::assertStringContainsString('scope="col"', $view, $path);
            self::assertStringContainsString('<x-erp.empty-state', $view, $path);
        }

        $employee = $this->contents('Modules/Employee/resources/views/employees/index.blade.php');
        self::assertStringContainsString('<x-erp.filter-toolbar', $employee);
        self::assertStringContainsString('<x-slot:pagination>', $employee);
        self::assertStringContainsString("@method('DELETE')", $employee);
        self::assertStringContainsString("confirm('Xóa khỏi danh sách hoạt động", $employee);

        $procurement = $this->contents('resources/views/admin/procurement/index.blade.php');
        self::assertStringContainsString('name="action" value="request.transition"', $procurement);
        self::assertStringContainsString('@csrf', $procurement);
        self::assertStringContainsString('<x-erp.dashboard class="procurement-command-center"', $procurement);
        self::assertStringContainsString('label="Open Purchase Requests"', $procurement);
        self::assertStringContainsString('label="Unpaid Supplier Bills"', $procurement);
        self::assertStringContainsString('label="Reverse workflow pending"', $procurement);
        self::assertStringNotContainsString('class="table-scroll"', $procurement);
        self::assertStringNotContainsString('class="data-table"', $procurement);
        self::assertStringContainsString('class="erp-table-scroll" tabindex="0" aria-label="Bảng dữ liệu Procurement"', $procurement);
        self::assertStringContainsString('<th scope="col">', $procurement);
        self::assertStringContainsString('<th scope="row">', $procurement);
        self::assertStringContainsString('<x-erp.status-badge :status="$v->status"', $procurement);
        self::assertStringContainsString('title="Không có Vendor Refund Due"', $procurement);
    }

    public function test_inventory_workspaces_use_enterprise_dashboard_tables_and_truthful_states(): void
    {
        $inventory = $this->contents('resources/views/admin/seller/inventory.blade.php');
        self::assertStringContainsString('<x-erp.dashboard title="Inventory command center"', $inventory);
        self::assertStringContainsString('label="On hand"', $inventory);
        self::assertStringContainsString('Current paginated result', $inventory);
        self::assertStringContainsString('<x-erp.data-table', $inventory);
        self::assertStringContainsString('scope="col"', $inventory);

        $operations = $this->contents('resources/views/admin/seller/inventory-movements.blade.php');
        self::assertStringContainsString('<x-erp.dashboard title="Warehouse operations command center"', $operations);
        self::assertStringContainsString('label="Receipt drafts"', $operations);
        self::assertStringContainsString('label="Transfers in transit"', $operations);
        self::assertStringContainsString('class="erp-table-scroll" tabindex="0"', $operations);
        self::assertStringContainsString('<th scope="col">', $operations);
        self::assertStringContainsString('<x-erp.status-badge', $operations);
        self::assertStringNotContainsString('class="table-scroll"', $operations);
        self::assertStringNotContainsString('class="data-table"', $operations);
        self::assertStringContainsString('hệ thống không clone chứng từ', $operations);

        $governance = $this->contents('resources/views/admin/seller/inventory-governance.blade.php');
        self::assertStringContainsString('<x-erp.dashboard title="Inventory governance command center"', $governance);
        self::assertStringContainsString('aria-label="Danh sách Stock Count"', $governance);
        self::assertStringContainsString('aria-label="Stock Count variance evidence"', $governance);
        self::assertStringContainsString('aria-label="Danh sách Inventory Adjustment"', $governance);
        self::assertStringContainsString('<x-erp.status-badge :status="$count->status"', $governance);
        self::assertStringContainsString('<x-erp.status-badge :status="$adjustment->status"', $governance);
        self::assertStringContainsString('không có direct adjustment fallback', $governance);

        $css = $this->contents('resources/css/app.css');
        self::assertStringContainsString('container-name:inventory-workspace', $css);
        self::assertStringContainsString('@container inventory-workspace (min-width:90rem)', $css);
        self::assertStringContainsString('@media(min-width:768px) and (max-width:1279px)', $css);
        self::assertStringContainsString('.inventory-operations-command-center .two-column-grid{grid-template-columns:1fr}', $css);
        self::assertStringContainsString('.inventory-operations-command-center :is(button,.button,.erp-button),.inventory-governance-command-center :is(button,.button,.erp-button){min-width:44px;min-height:44px}', $css);
        self::assertStringContainsString('font-size:16px', $css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $css);
        self::assertStringContainsString('@media(prefers-reduced-motion:reduce){.inventory-command-center .erp-kpi--link', $css);
        self::assertStringContainsString('.inventory-governance-command-center .erp-dashboard-section>.erp-widget-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,32rem),1fr))', $css);
        self::assertStringNotContainsString('admin-page-header page-hero panel', $inventory);
        self::assertStringNotContainsString('<section class="page-hero panel">', $operations);
        self::assertStringNotContainsString('<header class="admin-page-header">', $governance);
    }

    public function test_dms_uses_the_shared_page_header_and_status_component(): void
    {
        $index = $this->contents('Modules/Document/resources/views/index.blade.php');
        $show = $this->contents('Modules/Document/resources/views/show.blade.php');
        self::assertStringContainsString('<x-erp.page-header', $index);
        self::assertStringContainsString('<x-erp.page-header', $show);
        self::assertStringContainsString('<x-erp.status-badge', $show);
    }

    public function test_priority_business_forms_use_the_shared_enterprise_form_system(): void
    {
        foreach ([
            'resources/views/components/erp/form-shell.blade.php',
            'resources/views/components/erp/form-section.blade.php',
            'resources/views/components/erp/validation-summary.blade.php',
            'resources/views/components/erp/form-actions.blade.php',
            'resources/views/components/erp/detail-panel.blade.php',
        ] as $component) {
            self::assertFileExists(resource_path(str_replace('resources/views/', 'views/', $component)), $component);
        }

        foreach ([
            'Modules/Employee/resources/views/employees/create.blade.php',
            'Modules/Employee/resources/views/employees/edit.blade.php',
            'resources/views/admin/finance/fx-rates.blade.php',
            'resources/views/admin/seller/inventory-movements.blade.php',
            'resources/views/admin/procurement/index.blade.php',
        ] as $path) {
            $view = $this->contents($path);
            self::assertStringContainsString('<x-erp.form-shell', $view, $path);
            self::assertStringContainsString('@csrf', $view, $path);
        }

        $employee = $this->contents('Modules/Employee/resources/views/employees/partials/form.blade.php');
        self::assertStringContainsString('<x-erp.validation-summary', $employee);
        self::assertStringContainsString('<x-erp.form-actions>', $employee);
        self::assertStringContainsString('name="branch_ids[]"', $employee);
        self::assertStringContainsString('name="account_access_enabled"', $employee);

        $inventory = $this->contents('resources/views/admin/seller/inventory-movements.blade.php');
        self::assertStringContainsString("route('admin.seller.inventory.receipts.store')", $inventory);
        self::assertStringContainsString("route('admin.seller.inventory.transfers.store')", $inventory);

        $procurement = $this->contents('resources/views/admin/procurement/index.blade.php');
        self::assertStringContainsString('name="action" value="request.create"', $procurement);
        self::assertStringContainsString('name="procurement_classification"', $procurement);

        foreach ([
            'resources/views/admin/finance/accounting-adjustments.blade.php',
            'resources/views/admin/finance/fx-revaluations.blade.php',
            'resources/views/admin/seller/inventory-governance.blade.php',
        ] as $path) {
            $view = $this->contents($path);
            self::assertStringContainsString('<x-erp.form-shell', $view, $path);
            self::assertStringContainsString('<x-erp.form-actions>', $view, $path);
            self::assertStringContainsString('@csrf', $view, $path);
        }

        $profile = $this->contents('Modules/Employee/resources/views/employees/show.blade.php');
        self::assertStringContainsString('class="employee-profile erp-profile-layout"', $profile);
        self::assertStringContainsString('class="erp-detail-panel"', $profile);
        self::assertStringContainsString("confirm('Gỡ avatar", $profile);
        self::assertStringContainsString("confirm('Xác nhận thay đổi trạng thái", $profile);
    }

    public function test_shared_modal_restores_focus_and_exposes_dialog_semantics(): void
    {
        $modal = $this->contents('resources/views/components/modal.blade.php');
        $layout = $this->contents('resources/views/layouts/admin.blade.php');
        $css = $this->contents('resources/css/app.css');
        self::assertStringContainsString('returnFocus = document.activeElement', $modal);
        self::assertStringContainsString('returnFocus?.focus()', $modal);
        self::assertStringContainsString('x-on:keydown.escape.window', $modal);
        self::assertStringContainsString('role="dialog"', $modal);
        self::assertStringContainsString('aria-modal="true"', $modal);
        self::assertStringContainsString('aria-label=', $modal);
        self::assertStringContainsString('data-admin-help-dialog', $layout);
        self::assertStringContainsString('data-compensation-reference-dialog role="dialog" aria-modal="true"', $layout);
        self::assertSame(2, substr_count($layout, 'role="dialog"'));
        self::assertSame(2, substr_count($layout, 'aria-modal="true"'));
        self::assertStringContainsString('.primary-navigation .nav-item,.sidebar-display-tools button{min-height:var(--uix-control-height)}', $css);
    }

    public function test_kanban_workflow_and_onboarding_use_bounded_accessible_surfaces(): void
    {
        $css = $this->contents('resources/css/app.css');
        self::assertStringContainsString('.erp-board{', $css);
        self::assertStringContainsString('overflow-x:auto', $css);
        self::assertStringContainsString('.erp-board:focus-visible', $css);
        self::assertStringContainsString('@media(prefers-reduced-motion:reduce){.erp-board', $css);

        $pipeline = $this->contents('Modules/Recruitment/resources/views/applications/pipeline.blade.php');
        self::assertStringContainsString('class="erp-board" tabindex="0"', $pipeline);
        self::assertStringContainsString('data-realtime-refresh', $pipeline);
        self::assertStringContainsString('count($stageItems)', $pipeline);
        self::assertStringContainsString('role="listitem"', $pipeline);
        self::assertStringNotContainsString('draggable=', $pipeline);

        $tasks = $this->contents('Modules/Workflow/resources/views/tasks.blade.php');
        self::assertStringContainsString('<x-erp.data-table', $tasks);
        self::assertStringContainsString('scope="col"', $tasks);
        self::assertStringContainsString('@can(\'workflow.task.claim\')', $tasks);
        self::assertStringContainsString('name="idempotency_key"', $tasks);
        self::assertStringContainsString('@csrf', $tasks);

        $onboarding = $this->contents('Modules/Onboarding/resources/views/cases/index.blade.php');
        self::assertStringContainsString('<x-erp.data-table', $onboarding);
        self::assertStringContainsString('<x-erp.filter-toolbar>', $onboarding);
        self::assertStringContainsString('<x-slot:pagination>', $onboarding);
        self::assertStringContainsString('role="progressbar"', $onboarding);
        self::assertStringContainsString('Case chỉ được tạo qua quy trình tuyển dụng và hire hợp lệ', $onboarding);

        $section = $this->contents('resources/views/components/erp/dashboard-section.blade.php');
        $widget = $this->contents('resources/views/components/erp/widget.blade.php');
        self::assertStringContainsString('aria-labelledby="{{ $headingId }}"', $section);
        self::assertStringContainsString('role="alert"', $widget);
    }

    public function test_workflow_document_and_talent_details_use_truthful_accessible_surfaces(): void
    {
        $workflow = $this->contents('Modules/Workflow/resources/views/show.blade.php');
        self::assertStringContainsString('<x-erp.data-table', $workflow);
        self::assertStringContainsString('class="erp-timeline"', $workflow);
        self::assertStringContainsString("@can('workflow.task.decide')", $workflow);
        self::assertStringContainsString('name="idempotency_key"', $workflow);
        self::assertStringContainsString('@csrf', $workflow);

        $audit = $this->contents('Modules/Workflow/resources/views/audit.blade.php');
        self::assertStringContainsString('Sự kiện append-only', $audit);
        self::assertStringContainsString('scope="col"', $audit);

        $documents = $this->contents('Modules/Document/resources/views/index.blade.php');
        self::assertStringContainsString('không hiển thị nút duyệt giả', $documents);
        self::assertStringContainsString('<x-erp.data-table', $documents);
        self::assertStringContainsString("@can('document.create')", $documents);

        $document = $this->contents('Modules/Document/resources/views/show.blade.php');
        self::assertStringContainsString("@can('document.download')", $document);
        self::assertStringContainsString("confirm('Lưu trữ tài liệu này?')", $document);
        self::assertStringContainsString('@csrf', $document);

        $career = $this->contents('Modules/Performance/resources/views/career/dashboard.blade.php');
        self::assertStringContainsString('aria-label="Succession plans theo trạng thái"', $career);
        self::assertStringContainsString('không drag/drop', $career);
        self::assertStringNotContainsString('draggable=', $career);

        $careerEmployee = $this->contents('Modules/Performance/resources/views/career/employee.blade.php');
        self::assertStringContainsString('Append-only Career Timeline', $careerEmployee);
        self::assertStringContainsString('class="erp-timeline"', $careerEmployee);

        $performance = $this->contents('Modules/Performance/resources/views/employee.blade.php');
        self::assertStringContainsString('Review Timeline & Rating History', $performance);
        self::assertStringContainsString("@can('talent.review.approve')", $performance);
        self::assertStringContainsString('name="idempotency_key"', $performance);

        $css = $this->contents('resources/css/app.css');
        self::assertStringContainsString('.erp-workflow-summary{', $css);
        self::assertStringContainsString('.erp-timeline__item{', $css);
        self::assertStringContainsString('.erp-decision-form{', $css);
        self::assertStringContainsString('min-height:2.75rem', $css);
    }

    public function test_finance_close_and_fx_workspaces_use_enterprise_components_without_fake_actions(): void
    {
        $fiscal = $this->contents('resources/views/admin/finance/fiscal-years.blade.php');
        self::assertStringContainsString('<x-erp.dashboard ', $fiscal);
        self::assertStringContainsString('title="Trạng thái kỳ và close"', $fiscal);
        self::assertStringContainsString('name="idempotency_key"', $fiscal);
        self::assertStringContainsString("confirm('Xác nhận gửi yêu cầu đóng fiscal year?", $fiscal);

        $period = $this->contents('resources/views/admin/finance/period-close.blade.php');
        self::assertStringContainsString('<x-erp.dashboard ', $period);
        self::assertStringContainsString('aria-label="Danh sách kỳ kế toán"', $period);
        self::assertStringContainsString('<x-erp.status-badge', $period);
        self::assertStringContainsString('@csrf', $period);

        $fx = $this->contents('resources/views/admin/finance/fx-revaluations.blade.php');
        self::assertStringContainsString('aria-label="FX revaluation runs và source evidence"', $fx);
        self::assertStringContainsString("confirm('Xác nhận post FX revaluation", $fx);
        self::assertStringContainsString('erp-button erp-button--danger', $fx);
        self::assertStringContainsString('@csrf', $fx);
    }

    public function test_command_center_v2_uses_authorized_navigation_and_truthful_account_surfaces(): void
    {
        $layout = $this->contents('resources/views/layouts/admin.blade.php');
        self::assertStringContainsString('data-erp-command-header', $layout);
        self::assertStringContainsString('data-workspace-search', $layout);
        self::assertStringContainsString('data-account-menu', $layout);
        self::assertStringContainsString("route('profile.edit')", $layout);
        self::assertStringContainsString("route('logout')", $layout);
        self::assertStringContainsString('method="POST"', $layout);
        self::assertStringContainsString('@csrf', $layout);

        $dashboard = $this->contents('resources/views/admin/dashboard/index.blade.php');
        self::assertStringContainsString('AdminNavigation::class', $dashboard);
        self::assertStringContainsString('data-erp-ecosystem', $dashboard);
        self::assertStringContainsString("route(\$item['route'], \$item['parameters'])", $dashboard);
        self::assertStringContainsString('command-center-intro__actions', $dashboard);
        self::assertStringContainsString('data-compensation-reference-open', $dashboard);
        self::assertStringContainsString('data-admin-help-open', $dashboard);

        $profile = $this->contents('resources/views/profile/edit.blade.php');
        self::assertStringContainsString("@extends('layouts.admin')", $profile);
        self::assertStringContainsString('account-profile-summary', $profile);

        $processFlow = $this->contents('resources/views/components/admin-process-flow.blade.php');
        self::assertStringContainsString('Đường dẫn nghiệp vụ được cấp quyền', $processFlow);
        self::assertStringNotContainsString('Luồng đến bước hiện tại', $processFlow);

        $script = $this->contents('resources/js/admin/command-header.js');
        self::assertStringContainsString("event.key === 'Escape'", $script);
        self::assertStringContainsString("event.key !== 'ArrowDown'", $script);

        $overviewScript = $this->contents('resources/js/admin/overview-dashboard.js');
        self::assertStringNotContainsString("addEventListener('pointermove'", $overviewScript);
        self::assertStringNotContainsString('--executive-light-x', $overviewScript);
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(base_path($path));
        self::assertIsString($contents);

        return $contents;
    }
}
