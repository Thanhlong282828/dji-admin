<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Http\Request;

final class AdminNavigation
{
    /**
     * @return list<array{key:string,label:string,icon:string,active:bool,items:list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,active:bool}>}>
     */
    public function for(User $user, Request $request, int $companyId): array
    {
        $visible = [];
        foreach ($this->definitions($companyId) as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if ($user->can($item['permission'])) {
                    $item['active'] = $request->routeIs(...$item['patterns']);
                    $items[] = $item;
                }
            }
            if ($items !== []) {
                $visible[] = ['key' => $group['key'], 'label' => $group['label'], 'icon' => $group['icon'], 'active' => count(array_filter($items, static fn (array $item): bool => $item['active'])) > 0, 'items' => $items];
            }
        }

        return $visible;
    }

    /**
     * @return list<array{key:string,label:string,icon:string,items:list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,active:bool}>}>
     */
    public function definitions(int $companyId = 1): array
    {
        $company = ['company_id' => $companyId];

        return [
            $this->group('overview', 'ERP · Tổng quan', '◫', [
                $this->item('dashboard', 'Dashboard', 'dashboard', ['dashboard'], 'dashboard.view'),
            ]),
            $this->group('organization', 'HRM · Tổ chức', '⌂', [
                $this->item('companies', 'Công ty', 'admin.companies.index', ['admin.companies.*'], 'company.view'),
                $this->item('branches', 'Chi nhánh', 'admin.branches.index', ['admin.branches.*'], 'branch.view', $company),
                $this->item('cost-centers', 'Trung tâm chi phí', 'admin.cost-centers.index', ['admin.cost-centers.*'], 'cost-center.view', $company),
                $this->item('organization-units', 'Đơn vị tổ chức', 'admin.organization-units.index', ['admin.organization-units.index', 'admin.organization-units.show', 'admin.organization-units.edit'], 'organization-unit.view', $company),
                $this->item('organization-tree', 'Cây tổ chức', 'admin.organization-units.tree', ['admin.organization-units.tree'], 'organization-unit.view', $company),
                $this->item('job-positions', 'Vị trí công việc', 'admin.job-positions.index', ['admin.job-positions.*'], 'job-position.view', $company),
                $this->item('headcount', 'Báo cáo định biên', 'admin.organization.headcount', ['admin.organization.headcount'], 'organization-unit.view', $company),
            ]),
            $this->group('people', 'HRM · Nhân sự', '♙', [
                $this->item('employee-portal', 'Cổng thông tin nhân viên', 'employee.portal', ['employee.portal'], 'employee.portal.view'),
                $this->item('employees', 'Nhân viên', 'admin.employees.index', ['admin.employees.*'], 'employee.view', $company),
                $this->item('employee-lifecycle', 'Vòng đời nhân sự', 'admin.employee-lifecycle.index', ['admin.employee-lifecycle.*'], 'hr.lifecycle.view', $company),
                $this->item('hr-operations', 'Hợp đồng & Offboarding', 'admin.hr.operations.index', ['admin.hr.operations.*'], 'employment-contract.view', $company),
                $this->item('hr-governance', 'HR Data Governance', 'admin.hr.governance.dashboard', ['admin.hr.governance.*'], 'hr.governance.view', $company),
            ]),
            $this->group('workforce', 'HRM · Chấm công và nghỉ phép', '◷', [
                $this->item('attendance', 'Chấm công', 'admin.attendance.index', ['admin.attendance.*'], 'attendance.view', $company),
                $this->item('shifts', 'Ca và lịch làm việc', 'admin.workforce.management', ['admin.workforce.*'], 'shift.view', ['section' => 'shifts', ...$company]),
                $this->item('leave', 'Nghỉ phép', 'admin.leave.index', ['admin.leave.*'], 'leave.view', $company),
                $this->item('leave-management', 'Chính sách nghỉ phép', 'admin.leave-management.index', ['admin.leave-management.*'], 'leave.manage-policy', $company),
                $this->item('overtime', 'Tăng ca', 'admin.overtime.index', ['admin.overtime.*'], 'overtime.view', $company),
            ]),
            $this->group('talent', 'HRM · Tuyển dụng và phát triển', '◎', [
                $this->item('recruitment', 'Tổng quan tuyển dụng', 'admin.recruitment.dashboard', ['admin.recruitment.dashboard'], 'recruitment-requisition.view', $company),
                $this->item('requisitions', 'Yêu cầu tuyển dụng', 'admin.recruitment-requisitions.index', ['admin.recruitment-requisitions.*'], 'recruitment-requisition.view', $company),
                $this->item('candidates', 'Ứng viên', 'admin.candidates.index', ['admin.candidates.*'], 'candidate.view', $company),
                $this->item('applications', 'Pipeline ứng tuyển', 'admin.applications.pipeline', ['admin.applications.*'], 'application.view', $company),
                $this->item('interviews', 'Phỏng vấn', 'admin.interviews.index', ['admin.interviews.*'], 'interview.view', $company),
                $this->item('candidate-offers', 'Offer tuyển dụng', 'admin.candidate-offers.index', ['admin.candidate-offers.*'], 'candidate-offer.view', $company),
                $this->item('onboarding', 'Onboarding', 'admin.onboarding.dashboard', ['admin.onboarding.*', 'admin.onboarding-cases.*'], 'onboarding.view', $company),
                $this->item('performance', 'Hiệu suất và tài năng', 'admin.talent.performance.index', ['admin.talent.performance.*'], 'talent.performance.view', $company),
                $this->item('career', 'Career và kế nhiệm', 'admin.career.index', ['admin.career.*'], 'career.view', $company),
            ]),
            $this->group('payroll', 'Payroll', '◇', [
                $this->item('payroll-dashboard', 'Cấu hình lương', 'admin.payroll.delivery.index', ['admin.payroll.delivery.*', 'admin.payroll.components.*', 'admin.payroll.assignments.*'], 'payroll.component.view', $company),
                $this->item('payroll-periods', 'Kỳ lương', 'admin.payroll.periods.index', ['admin.payroll.periods.*'], 'payroll-period.view', $company),
                $this->item('payroll-inputs', 'Payroll Inputs', 'admin.payroll.inputs.index', ['admin.payroll.inputs.*', 'admin.payroll-period.*'], 'payroll-period.view', $company),
                $this->item('payroll-runs', 'Payroll Runs', 'admin.payroll.runs.index', ['admin.payroll.runs.*'], 'payroll.run.view', $company),
                $this->item('payroll-adjustments', 'Điều chỉnh lương', 'admin.payroll.adjustments.index', ['admin.payroll.adjustments.*'], 'payroll.adjustment.view', $company),
                $this->item('branch-bonuses', 'Thưởng chi nhánh', 'admin.branch-bonuses.index', ['admin.branch-bonuses.*'], 'branch.bonus.view', $company),
                $this->item('payslips', 'Phiếu lương', 'admin.payroll.payslips.index', ['admin.payroll.payslips.*'], 'payroll.payslip.view', $company),
                $this->item('payments', 'Thanh toán', 'admin.payroll.payment-batches.index', ['admin.payroll.payment-accounts.*', 'admin.payroll.payment-batches.*'], 'payroll.payment.view', $company),
            ]),
            $this->group('tax-statutory', 'Thuế & Bảo hiểm bắt buộc', '§', [
                $this->item('finance-tax-dashboard', 'Tổng quan thuế & nghĩa vụ', 'admin.finance.tax-dashboard', ['admin.finance.tax-dashboard'], 'payroll.tax.view', $company),
                $this->item('tax-administration', 'Hồ sơ thuế TNCN', 'admin.payroll.tax.employees.index', ['admin.payroll.tax.employees.*'], 'payroll.tax.view', $company),
                $this->item('tax-policies', 'Chính sách thuế', 'admin.payroll.tax.policies.index', ['admin.payroll.tax.policies.*'], 'payroll.tax.view', $company),
                $this->item('insurance-administration', 'Hồ sơ BHXH/BHYT/BHTN', 'admin.payroll.insurance.employees.index', ['admin.payroll.insurance.employees.*'], 'payroll.insurance.view', $company),
                $this->item('insurance-policies', 'Chính sách bảo hiểm', 'admin.payroll.insurance.policies.index', ['admin.payroll.insurance.policies.*'], 'payroll.insurance.view', $company),
                $this->item('annual-tax', 'Đối soát thuế TNCN năm', 'admin.payroll.annual-tax.index', ['admin.payroll.annual-tax.*'], 'payroll.annual_tax.view', $company),
                $this->item('government', 'Government Filing', 'admin.payroll.government-filings.index', ['admin.payroll.government-filings.*'], 'government.filing.view', $company),
            ]),
            $this->group('finance', 'Tài chính', '₫', [
                $this->item('finance-overview', 'Tổng quan tài chính', 'admin.finance.overview', ['admin.finance.overview'], 'payroll.accounting.view', $company),
                $this->item('cost-center-cost-management', 'Chi phí theo Cost Center', 'admin.finance.cost-center-costs', ['admin.finance.cost-center-costs'], 'finance.operations.view', $company),
                $this->item('sales-receivable', 'Sales · AR · Collection', 'admin.sales.index', ['admin.sales.*'], 'sales.view', $company),
                $this->item('procurement', 'Mua hàng & Công nợ phải trả', 'admin.procurement.page', ['admin.procurement.*'], 'procurement.view', $company + ['page' => 'vendors']),
                $this->item('finance-operations', 'Chi phí · Tài sản · Ngân sách', 'admin.finance.operations', ['admin.finance.operations'], 'finance.operations.view', $company + ['page' => 'expenses']),
                $this->item('treasury', 'Treasury · Dòng tiền', 'admin.finance.treasury', ['admin.finance.treasury*'], 'treasury.view', $company),
                $this->item('accounting-period-close', 'Accounting Period Close', 'admin.finance.period-close', ['admin.finance.period-close*'], 'accounting.period.view', $company),
                $this->item('accounting-adjustments', 'Accounting Adjustments', 'admin.finance.accounting-adjustments', ['admin.finance.accounting-adjustments*'], 'accounting.adjustment.view', $company),
                $this->item('branch-financial-performance', 'Hiệu quả tài chính chi nhánh', 'admin.finance.branch-performance', ['admin.finance.branch-performance'], 'payroll.assignment.view', $company),
                $this->item('payroll-finance-reconciliation', 'Đối soát Payroll', 'admin.finance.payroll-reconciliation', ['admin.finance.payroll-reconciliation'], 'payroll.accounting.view', $company),
                $this->item('financial-statements', 'Sổ cái & Báo cáo tài chính', 'admin.finance.financial-statements', ['admin.finance.financial-statements'], 'payroll.accounting.view', $company),
                $this->item('accounting', 'Kế toán và bút toán', 'admin.payroll.accounting.journals.index', ['admin.payroll.accounting.journals.*'], 'payroll.accounting.view', $company),
                $this->item('accounting-configuration', 'Cấu hình kế toán', 'admin.payroll.accounting.policies.index', ['admin.payroll.accounting.policies.*', 'admin.payroll.accounting.accounts.*', 'admin.payroll.accounting.mappings.*'], 'payroll.accounting.view', $company),
                $this->item('erp', 'ERP Export', 'admin.payroll.erp.exports.index', ['admin.payroll.erp.exports.*'], 'erp.integration.view', $company),
                $this->item('erp-configuration', 'Kết nối ERP', 'admin.payroll.erp.connections.index', ['admin.payroll.erp.connections.*', 'admin.payroll.erp.mappings.*'], 'erp.integration.connection.view', $company),
            ]),
            $this->group('supply-chain', 'Chuỗi cung ứng', '⇄', [
                $this->item('supply-chain-overview', 'Tổng quan chuỗi cung ứng', 'admin.supply-chain.index', ['admin.supply-chain.*'], 'inventory.view', $company),
            ]),
            $this->group('projects', 'Projects', '◈', [
                $this->item('project-overview', 'Tổng quan dự án', 'admin.projects.overview', ['admin.projects.overview'], 'project.view', $company),
                $this->item('projects', 'Dự án', 'admin.projects.index', ['admin.projects.index', 'admin.projects.show', 'admin.projects.update', 'admin.projects.status', 'admin.projects.members.*', 'admin.projects.milestones.*', 'admin.projects.tasks.*', 'admin.projects.dependencies.*', 'admin.projects.timesheets.*'], 'project.view', $company),
            ]),
            $this->group('asset-maintenance', 'Tài sản & Bảo trì', '⚙', [
                $this->item('maintenance-overview', 'Tổng quan bảo trì', 'admin.maintenance.overview', ['admin.maintenance.overview'], 'maintenance.view', $company),
                $this->item('maintenance-requests', 'Yêu cầu bảo trì', 'admin.maintenance.requests', ['admin.maintenance.requests', 'admin.maintenance.requests.*'], 'maintenance.view', $company),
                $this->item('maintenance-work-orders', 'Lệnh công việc', 'admin.maintenance.work-orders', ['admin.maintenance.work-orders', 'admin.maintenance.work-orders.*'], 'maintenance.view', $company),
                $this->item('maintenance-plans', 'Bảo trì định kỳ', 'admin.maintenance.plans', ['admin.maintenance.plans', 'admin.maintenance.plans.*'], 'maintenance.view', $company),
            ]),
            $this->group('analytics-planning', 'Analytics & Planning', '▥', [
                $this->item('executive-dashboard', 'Executive Dashboard', 'admin.executive.index', ['admin.executive.index'], 'analytics.view', $company),
                $this->item('enterprise-analytics', 'Phân tích điều hành', 'admin.analytics.enterprise', ['admin.analytics.enterprise'], 'analytics.view', $company),
                $this->item('analytics', 'Enterprise Analytics', 'admin.analytics.workforce', ['admin.analytics.workforce', 'admin.analytics.export', 'admin.analytics.generate', 'admin.analytics.mutate'], 'analytics.view', $company),
                $this->item('forecasting', 'Workforce Forecasting', 'admin.analytics.forecasting', ['admin.analytics.forecasting*'], 'analytics.view', $company),
                $this->item('executive-planning', 'Executive Planning', 'admin.planning.executive', ['admin.planning.executive', 'admin.planning.export'], 'analytics.view', $company),
                $this->item('planning-execution', 'Planning Execution', 'admin.planning.execution', ['admin.planning.execution*'], 'analytics.view', $company),
                $this->item('enterprise-reports', 'Enterprise Reports', 'admin.reports.index', ['admin.reports.*'], 'analytics.view', $company),
            ]),
            $this->group('automation', 'Tự động hóa doanh nghiệp', '⌁', [
                $this->item('enterprise-documents', 'Kho tài liệu', 'admin.documents.index', ['admin.documents.*'], 'document.view', $company),
                $this->item('approval-inbox', 'Approval Inbox', 'admin.approvals.index', ['admin.approvals.*'], 'approval.inbox.view', $company),
                $this->item('workflow', 'Workflow Center', 'admin.workflows.dashboard', ['admin.workflows.dashboard', 'admin.workflows.instances.*'], 'workflow.view', $company),
                $this->item('workflow-definitions', 'Workflow Definitions', 'admin.workflows.definitions', ['admin.workflows.definitions*'], 'workflow.view', $company),
                $this->item('workflow-tasks', 'Workflow Tasks', 'admin.workflows.tasks', ['admin.workflows.tasks*'], 'workflow.view', $company),
                $this->item('notifications', 'Notification Center', 'admin.notifications.index', ['admin.notifications.index', 'admin.notifications.show'], 'notification.view', $company),
                $this->item('notification-templates', 'Notification Templates', 'admin.notifications.templates.index', ['admin.notifications.templates.*'], 'notification.template.manage', $company),
                $this->item('notification-deliveries', 'Notification Deliveries', 'admin.notifications.deliveries.index', ['admin.notifications.deliveries.*'], 'notification.delivery.view', $company),
                $this->item('integrations', 'Integration Hub', 'admin.integrations.dashboard', ['admin.integrations.*'], 'integration.view', $company),
            ]),
            $this->group('access', 'IAM và Audit', '◆', [
                $this->item('iam', 'IAM Governance', 'admin.iam.dashboard', ['admin.iam.dashboard', 'admin.iam.access-360'], 'iam.view', $company),
                $this->item('access-requests', 'Access Requests', 'admin.iam.requests.index', ['admin.iam.requests.*'], 'iam.view', $company),
                $this->item('roles-permissions', 'Roles & Permissions', 'admin.iam.roles', ['admin.iam.roles'], 'iam.view', $company),
                $this->item('privileged', 'Privileged Access', 'admin.iam.privileged', ['admin.iam.privileged'], 'iam.privileged.manage', $company),
                $this->item('segregation-of-duties', 'Segregation of Duties', 'admin.iam.sod', ['admin.iam.sod'], 'iam.sod.manage', $company),
                $this->item('access-reviews', 'Access Reviews', 'admin.iam.reviews', ['admin.iam.reviews*'], 'iam.review.manage', $company),
                $this->item('audit', 'Audit Platform', 'admin.audit.dashboard', ['admin.audit.*'], 'audit.view', $company),
            ]),
            $this->group('operations', 'Vận hành nền tảng', '◉', [
                $this->item('reliability', 'Reliability', 'admin.operations.reliability', ['admin.operations.reliability'], 'operations.view'),
                $this->item('observability', 'Observability', 'admin.operations.observability', ['admin.operations.observability*'], 'operations.view'),
                $this->item('performance-engineering', 'Performance', 'admin.operations.performance', ['admin.operations.performance'], 'operations.view'),
                $this->item('architecture-governance', 'Architecture', 'admin.operations.architecture', ['admin.operations.architecture'], 'operations.view'),
                $this->item('test-quality', 'Test Quality', 'admin.operations.test-quality', ['admin.operations.test-quality'], 'operations.view'),
                $this->item('api-contracts', 'API Contracts', 'admin.operations.api-contracts', ['admin.operations.api-contracts'], 'operations.view'),
                $this->item('releases', 'Releases', 'admin.operations.releases', ['admin.operations.releases'], 'operations.view'),
                $this->item('configuration', 'Runtime Configuration', 'admin.operations.configuration', ['admin.operations.configuration'], 'operations.view'),
                $this->item('secrets', 'Secrets', 'admin.operations.secrets', ['admin.operations.secrets'], 'operations.view'),
                $this->item('data-protection', 'Data Protection', 'admin.operations.data-protection', ['admin.operations.data-protection'], 'operations.view'),
                $this->item('data-lifecycle', 'Data Lifecycle', 'admin.operations.data-lifecycle', ['admin.operations.data-lifecycle'], 'operations.view'),
                $this->item('data-quality', 'Data Quality', 'admin.operations.data-quality', ['admin.operations.data-quality'], 'operations.view'),
                $this->item('continuity', 'Business Continuity', 'admin.operations.continuity', ['admin.operations.continuity'], 'operations.view'),
                $this->item('security-assurance', 'Security Assurance', 'admin.operations.security-assurance', ['admin.operations.security-assurance'], 'operations.view'),
                $this->item('go-live-readiness', 'Go-Live Readiness', 'admin.operations.go-live', ['admin.operations.go-live'], 'operations.view'),
                $this->item('api-documentation', 'API Documentation', 'admin.operations.api-documentation', ['admin.operations.api-documentation'], 'api.documentation.view'),
            ]),
            $this->group('seller', 'CRM & Commerce', '▣', [
                $this->item('seller-dashboard', 'Tổng quan Seller', 'admin.seller.dashboard', ['admin.seller.dashboard'], 'commerce.store.view', $company),
                $this->item('store-profile', 'Hồ sơ cửa hàng', 'admin.seller.store', ['admin.seller.store*'], 'commerce.store.view', $company),
                $this->item('products', 'Sản phẩm', 'admin.products.index', ['admin.products.*'], 'product.view', $company),
                $this->item('seller-inventory', 'Tồn kho', 'admin.seller.inventory', ['admin.seller.inventory'], 'inventory.view', $company),
                $this->item('inventory-movements', 'Lịch sử biến động kho', 'admin.seller.inventory.movements', ['admin.seller.inventory.movements'], 'inventory.view', $company),
                $this->item('seller-orders', 'Đơn hàng', 'admin.seller.orders.index', ['admin.seller.orders.*'], 'commerce.store.view', $company),
                $this->item('commerce-sales-history', 'Lịch sử đơn hàng đã bán', 'admin.commerce.sales-history', ['admin.commerce.sales-history'], 'commerce.payment.view', $company),
                $this->item('commerce-payments', 'Commerce Payments', 'admin.commerce.payments.index', ['admin.commerce.payments.*'], 'commerce.payment.view', $company),
                $this->item('product-categories', 'Danh mục sản phẩm', 'admin.product-categories.index', ['admin.product-categories.*'], 'product-category.view'),
                $this->item('seller-governance', 'Quản trị Seller', 'admin.commerce.sellers.index', ['admin.commerce.sellers.*'], 'commerce.seller.governance.view', $company),
                $this->item('articles', 'Kho nội dung', 'articles.index', ['articles.index', 'articles.edit'], 'article.view', $company),
                $this->item('article-create', 'Viết bài mới', 'articles.create', ['articles.create'], 'article.create', $company),
            ]),
            $this->group('pos', 'POS / Bán hàng tại quầy', '▦', [
                $this->item('pos-dashboard', 'Tổng quan POS', 'admin.branch-commerce.dashboard', ['admin.branch-commerce.dashboard'], 'branch-commerce.view', $company),
                $this->item('customer-checkins', 'Thu ngân · Check-in / Checkout', 'admin.branch-commerce.checkins', ['admin.branch-commerce.checkins*', 'admin.branch-commerce.checkout*'], 'branch-commerce.view', $company),
                $this->item('branch-customers', 'Tệp khách hàng', 'admin.branch-commerce.customers', ['admin.branch-commerce.customers*'], 'branch-commerce.view', $company),
                $this->item('company-services', 'Dịch vụ công ty', 'admin.branch-commerce.services', ['admin.branch-commerce.services*'], 'branch-commerce.view', $company),
                $this->item('branch-warehouse', 'Kho POS / Tồn kho chi nhánh', 'admin.branch-commerce.warehouse', ['admin.branch-commerce.warehouse'], 'branch-commerce.view', $company),
                $this->item('branch-sales', 'Phiếu thu · Doanh số POS', 'admin.branch-commerce.sales', ['admin.branch-commerce.sales'], 'branch-commerce.view', $company),
                $this->item('pos-receipts', 'Lịch sử phiếu thu', 'admin.branch-commerce.receipts', ['admin.branch-commerce.receipts*'], 'branch-commerce.view', $company),
            ]),
        ];
    }

    /** @param list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,active:bool}> $items
     * @return array{key:string,label:string,icon:string,items:list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,active:bool}>}
     */
    private function group(string $key, string $label, string $icon, array $items): array
    {
        return compact('key', 'label', 'icon', 'items');
    }

    /** @param list<string> $patterns
     * @param  array<string, int|string>  $parameters
     * @return array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,active:bool}
     */
    private function item(string $key, string $label, string $route, array $patterns, string $permission, array $parameters = []): array
    {
        return compact('key', 'label', 'route', 'parameters', 'patterns', 'permission') + ['active' => false];
    }
}
