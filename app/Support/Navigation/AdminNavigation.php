<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminNavigation
{
    /**
     * @return list<array{key:string,label:string,icon:string,active:bool,items:list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,process_help:bool,active:bool}>}>
     */
    public function for(User $user, Request $request, int $companyId): array
    {
        $context = $this->contextAvailability($user, $companyId);
        $visible = [];
        foreach ($this->definitions($companyId) as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if ($user->can($item['permission']) && ($context[$item['key']] ?? true)) {
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

    /** @return array<string, bool> */
    private function contextAvailability(User $user, int $companyId): array
    {
        $hasEmployeePortal = Schema::hasTable('employees')
            && DB::table('employees')->where('user_id', $user->id)->whereNull('deleted_at')->exists();

        $hasAccessibleBranch = false;
        if (Schema::hasTable('branches')) {
            $branches = DB::table('branches as b')
                ->where('b.company_id', $companyId)
                ->whereNull('b.deleted_at');
            if (! $user->can('organization.tenant-all')) {
                $branches->whereExists(static function (Builder $query) use ($user): void {
                    $query->selectRaw('1')
                        ->from('branch_user_access as bua')
                        ->whereColumn('bua.branch_id', 'b.id')
                        ->where('bua.user_id', $user->id);
                });
            }
            $hasAccessibleBranch = $branches->exists();
        }

        return [
            'employee-portal' => $hasEmployeePortal,
            'branch-warehouse' => $hasAccessibleBranch,
        ];
    }

    /**
     * @return list<array{key:string,label:string,icon:string,items:list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,process_help:bool,active:bool}>}>
     */
    public function definitions(int $companyId = 1): array
    {
        $company = ['company_id' => $companyId];

        $groups = [
            $this->group('overview', 'ERP · Hoạch định nguồn lực', '◫', [
                $this->item('dashboard', 'Dashboard', 'dashboard', ['dashboard'], 'dashboard.view'),
                $this->item('companies', 'Công ty', 'admin.companies.index', ['admin.companies.*'], 'company.view'),
                $this->item('branches', 'Chi nhánh', 'admin.branches.index', ['admin.branches.*'], 'branch.view', $company),
                $this->item('cost-centers', 'Trung tâm chi phí', 'admin.cost-centers.index', ['admin.cost-centers.*'], 'cost-center.view', $company),
                $this->item('organization-units', 'Đơn vị tổ chức', 'admin.organization-units.index', ['admin.organization-units.index', 'admin.organization-units.show', 'admin.organization-units.edit'], 'organization-unit.view', $company),
                $this->item('organization-tree', 'Cây tổ chức', 'admin.organization-units.tree', ['admin.organization-units.tree'], 'organization-unit.view', $company),
                $this->item('job-positions', 'Vị trí công việc', 'admin.job-positions.index', ['admin.job-positions.*'], 'job-position.view', $company),
                $this->item('headcount', 'Báo cáo định biên', 'admin.organization.headcount', ['admin.organization.headcount'], 'organization-unit.view', $company),
                $this->item('project-overview', 'Tổng quan dự án', 'admin.projects.overview', ['admin.projects.overview'], 'project.view', $company),
                $this->item('projects', 'Dự án', 'admin.projects.index', ['admin.projects.index', 'admin.projects.show', 'admin.projects.update', 'admin.projects.status', 'admin.projects.members.*', 'admin.projects.milestones.*', 'admin.projects.tasks.*', 'admin.projects.dependencies.*', 'admin.projects.timesheets.*'], 'project.view', $company),
            ]),
            $this->group('people', 'HRM · Nhân sự cốt lõi', '♙', [
                $this->item('employee-portal', 'Cổng thông tin nhân viên', 'employee.portal', ['employee.portal'], 'employee.portal.view'),
                $this->item('employees', 'Nhân viên', 'admin.employees.index', ['admin.employees.*'], 'employee.view', $company),
                $this->item('employee-lifecycle', 'Vòng đời nhân sự', 'admin.employee-lifecycle.index', ['admin.employee-lifecycle.*'], 'hr.lifecycle.view', $company),
                $this->item('hr-operations', 'Hợp đồng & Offboarding', 'admin.hr.operations.index', ['admin.hr.operations.*'], 'employment-contract.view', $company),
                $this->item('employee-documents', 'Hồ sơ tài liệu', 'admin.hr.governance.documents', ['admin.hr.governance.documents'], 'hr.governance.view', $company),
                $this->item('employee-consents', 'Chấp thuận dữ liệu', 'admin.hr.governance.consents', ['admin.hr.governance.consents'], 'hr.governance.view', $company),
                $this->item('hr-governance', 'HR Data Governance', 'admin.hr.governance.dashboard', ['admin.hr.governance.*'], 'hr.governance.view', $company),
            ]),
            $this->group('workforce', 'HRM · Chấm công & thời gian', '◷', [
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
            $this->group('payroll', 'HRM · Payroll', '◇', [
                $this->item('payroll-dashboard', 'Cấu hình lương', 'admin.payroll.delivery.index', ['admin.payroll.delivery.*', 'admin.payroll.components.*', 'admin.payroll.assignments.*'], 'payroll.component.view', $company),
                $this->item('payroll-periods', 'Kỳ lương', 'admin.payroll.periods.index', ['admin.payroll.periods.*'], 'payroll-period.view', $company),
                $this->item('payroll-inputs', 'Payroll Inputs', 'admin.payroll.inputs.index', ['admin.payroll.inputs.*', 'admin.payroll-period.*'], 'payroll-period.view', $company),
                $this->item('payroll-runs', 'Payroll Runs', 'admin.payroll.runs.index', ['admin.payroll.runs.*'], 'payroll.run.view', $company),
                $this->item('payroll-adjustments', 'Điều chỉnh lương', 'admin.payroll.adjustments.index', ['admin.payroll.adjustments.*'], 'payroll.adjustment.view', $company),
                $this->item('branch-bonuses', 'Thưởng chi nhánh', 'admin.branch-bonuses.index', ['admin.branch-bonuses.*'], 'branch.bonus.view', $company),
                $this->item('payslips', 'Phiếu lương', 'admin.payroll.payslips.index', ['admin.payroll.payslips.*'], 'payroll.payslip.view', $company),
                $this->item('payments', 'Thanh toán', 'admin.payroll.payment-batches.index', ['admin.payroll.payment-accounts.*', 'admin.payroll.payment-batches.*'], 'payroll.payment.view', $company),
            ]),
            $this->group('tax-statutory', 'FMS · Thuế & nghĩa vụ', '§', [
                $this->item('finance-tax-dashboard', 'Tổng quan thuế & nghĩa vụ', 'admin.finance.tax-dashboard', ['admin.finance.tax-dashboard'], 'payroll.tax.view', $company),
                $this->item('vat-policy-workspace', 'VAT Policy Workspace', 'admin.finance.tax-workspace', ['admin.finance.tax-workspace'], 'tax-policy.view', $company, true),
                $this->item('tax-administration', 'Hồ sơ thuế TNCN', 'admin.payroll.tax.employees.index', ['admin.payroll.tax.employees.*'], 'payroll.tax.view', $company),
                $this->item('tax-policies', 'Chính sách thuế', 'admin.payroll.tax.policies.index', ['admin.payroll.tax.policies.*'], 'payroll.tax.view', $company),
                $this->item('insurance-administration', 'Hồ sơ BHXH/BHYT/BHTN', 'admin.payroll.insurance.employees.index', ['admin.payroll.insurance.employees.*'], 'payroll.insurance.view', $company),
                $this->item('insurance-policies', 'Chính sách bảo hiểm', 'admin.payroll.insurance.policies.index', ['admin.payroll.insurance.policies.*'], 'payroll.insurance.view', $company),
                $this->item('annual-tax', 'Đối soát thuế TNCN năm', 'admin.payroll.annual-tax.index', ['admin.payroll.annual-tax.*'], 'payroll.annual_tax.view', $company),
                $this->item('government', 'Government Filing', 'admin.payroll.government-filings.index', ['admin.payroll.government-filings.*'], 'government.filing.view', $company),
            ]),
            $this->group('crm', 'CRM · Lead & Opportunity', '◉', [
                $this->item('crm-pipeline', 'Pipeline', 'admin.crm.index', ['admin.crm.index'], 'crm.view', $company, true),
                $this->item('crm-leads', 'Leads', 'admin.crm.leads', ['admin.crm.leads'], 'crm.view', $company),
                $this->item('crm-opportunities', 'Opportunities', 'admin.crm.opportunities', ['admin.crm.opportunities'], 'crm.view', $company),
            ]),
            $this->group('procurement', 'PMS · Mua hàng', '✓', [
                $this->item('procurement-vendors', 'Nhà cung cấp', 'admin.procurement.vendors', ['admin.procurement.vendors'], 'vendor.view', $company),
                $this->item('purchase-requests', 'Yêu cầu mua hàng', 'admin.procurement.requests', ['admin.procurement.requests'], 'purchase-request.view', $company),
                $this->item('purchase-orders', 'Đơn mua hàng', 'admin.procurement.orders', ['admin.procurement.orders'], 'purchase-order.view', $company),
                $this->item('goods-receipts', 'Phiếu nhận hàng', 'admin.procurement.receipts', ['admin.procurement.receipts'], 'goods-receipt.view', $company),
                $this->item('goods-returns', 'Trả hàng nhà cung cấp', 'admin.procurement.goods-returns', ['admin.procurement.goods-returns'], 'procurement-goods-return.view', $company),
                $this->item('supplier-credits', 'Giảm trừ công nợ NCC', 'admin.procurement.supplier-credits', ['admin.procurement.supplier-credits'], 'supplier-credit.view', $company),
                $this->item('service-acceptances', 'Nghiệm thu dịch vụ', 'admin.procurement.service-acceptances', ['admin.procurement.service-acceptances'], 'service-acceptance.view', $company),
                $this->item('vendor-bills', 'Hóa đơn NCC · AP', 'admin.procurement.payables', ['admin.procurement.payables'], 'vendor-bill.view', $company),
                $this->item('procurement-reports', 'Báo cáo mua hàng', 'admin.procurement.reports', ['admin.procurement.reports'], 'procurement.view', $company),
            ]),
            $this->group('finance', 'FMS · Tài chính & kế toán', '₫', [
                $this->item('finance-overview', 'Tổng quan tài chính', 'admin.finance.overview', ['admin.finance.overview'], 'payroll.accounting.view', $company),
                $this->item('cost-center-cost-management', 'Chi phí theo Cost Center', 'admin.finance.cost-center-costs', ['admin.finance.cost-center-costs'], 'finance.operations.view', $company),
                $this->item('finance-operations', 'Chi phí · Tài sản · Ngân sách', 'admin.finance.operations', ['admin.finance.operations'], 'finance.operations.view', $company + ['page' => 'expenses']),
                $this->item('treasury', 'Treasury · Dòng tiền', 'admin.finance.treasury', ['admin.finance.treasury*'], 'treasury.view', $company, true),
                $this->item('accounting-period-close', 'Accounting Period Close', 'admin.finance.period-close', ['admin.finance.period-close*'], 'accounting.period.view', $company),
                $this->item('accounting-adjustments', 'Accounting Adjustments', 'admin.finance.accounting-adjustments', ['admin.finance.accounting-adjustments*'], 'accounting.adjustment.view', $company),
                $this->item('branch-financial-performance', 'Hiệu quả tài chính chi nhánh', 'admin.finance.branch-performance', ['admin.finance.branch-performance'], 'payroll.assignment.view', $company),
                $this->item('payroll-finance-reconciliation', 'Đối soát Payroll', 'admin.finance.payroll-reconciliation', ['admin.finance.payroll-reconciliation'], 'payroll.accounting.view', $company),
                $this->item('financial-statements', 'Sổ cái & Báo cáo tài chính', 'admin.finance.financial-statements', ['admin.finance.financial-statements'], 'payroll.accounting.view', $company),
                $this->item('fiscal-years', 'Fiscal Year Governance', 'admin.finance.fiscal-years', ['admin.finance.fiscal-years*'], 'accounting.period.view', $company, true),
                $this->item('chart-of-accounts', 'Hệ thống tài khoản', 'admin.payroll.accounting.accounts.index', ['admin.payroll.accounting.accounts.*'], 'payroll.accounting.view', $company),
                $this->item('accounting', 'Bút toán nhật ký', 'admin.payroll.accounting.journals.index', ['admin.payroll.accounting.journals.*'], 'payroll.accounting.view', $company),
                $this->item('accounting-policy-workspace', 'Accounting Policy Workspace', 'admin.finance.accounting-policies', ['admin.finance.accounting-policies'], 'payroll.accounting.view', $company, true),
                $this->item('fx-rate-governance', 'FX Rate Governance', 'admin.finance.fx-rate', ['admin.finance.fx-rate*'], 'finance.fx-rate.view', $company, true),
                $this->item('fx-exposure', 'FX Exposure', 'admin.finance.fx-exposure', ['admin.finance.fx-exposure'], 'finance.fx-exposure.view', $company, true),
                $this->item('fx-revaluation', 'FX Revaluation', 'admin.finance.fx-revaluation', ['admin.finance.fx-revaluation*'], 'finance.fx-revaluation.view', $company, true),
                $this->item('accounting-configuration', 'Posting Rules', 'admin.payroll.accounting.policies.index', ['admin.payroll.accounting.policies.*', 'admin.payroll.accounting.mappings.*'], 'payroll.accounting.view', $company),
                $this->item('erp', 'ERP Export', 'admin.payroll.erp.exports.index', ['admin.payroll.erp.exports.*'], 'erp.integration.view', $company),
                $this->item('erp-configuration', 'Kết nối ERP', 'admin.payroll.erp.connections.index', ['admin.payroll.erp.connections.*', 'admin.payroll.erp.mappings.*'], 'erp.integration.connection.view', $company),
            ]),
            $this->group('supply-chain', 'SCM · Chuỗi cung ứng', '⇄', [
                $this->item('supply-chain-overview', 'Tổng quan chuỗi cung ứng', 'admin.supply-chain.index', ['admin.supply-chain.*'], 'inventory.view', $company),
            ]),
            $this->group('asset-maintenance', 'EAM · Tài sản & bảo trì', '⚙', [
                $this->item('maintenance-overview', 'Tổng quan bảo trì', 'admin.maintenance.overview', ['admin.maintenance.overview'], 'maintenance.view', $company),
                $this->item('maintenance-requests', 'Yêu cầu bảo trì', 'admin.maintenance.requests', ['admin.maintenance.requests', 'admin.maintenance.requests.*'], 'maintenance.view', $company),
                $this->item('maintenance-work-orders', 'Lệnh công việc', 'admin.maintenance.work-orders', ['admin.maintenance.work-orders', 'admin.maintenance.work-orders.*'], 'maintenance.view', $company),
                $this->item('maintenance-plans', 'Bảo trì định kỳ', 'admin.maintenance.plans', ['admin.maintenance.plans', 'admin.maintenance.plans.*'], 'maintenance.view', $company),
            ]),
            $this->group('analytics-planning', 'BI · Phân tích & hoạch định', '▥', [
                $this->item('executive-dashboard', 'Executive Dashboard', 'admin.executive.index', ['admin.executive.index'], 'analytics.view', $company),
                $this->item('enterprise-analytics', 'Phân tích điều hành', 'admin.analytics.enterprise', ['admin.analytics.enterprise'], 'analytics.view', $company),
                $this->item('analytics', 'Enterprise Analytics', 'admin.analytics.workforce', ['admin.analytics.workforce', 'admin.analytics.export', 'admin.analytics.generate', 'admin.analytics.mutate'], 'analytics.view', $company),
                $this->item('forecasting', 'Workforce Forecasting', 'admin.analytics.forecasting', ['admin.analytics.forecasting*'], 'analytics.view', $company),
                $this->item('executive-planning', 'Executive Planning', 'admin.planning.executive', ['admin.planning.executive', 'admin.planning.export'], 'analytics.view', $company),
                $this->item('planning-execution', 'Planning Execution', 'admin.planning.execution', ['admin.planning.execution*'], 'analytics.view', $company),
                $this->item('enterprise-reports', 'Enterprise Reports', 'admin.reports.index', ['admin.reports.*'], 'analytics.view', $company),
            ]),
            $this->group('documents', 'DMS · Tài liệu doanh nghiệp', '▤', [
                $this->item('enterprise-documents', 'Kho tài liệu', 'admin.documents.index', ['admin.documents.*'], 'document.view', $company),
                $this->item('approval-inbox', 'Approval Inbox', 'admin.approvals.index', ['admin.approvals.*'], 'approval.inbox.view', $company),
            ]),
            $this->group('automation', 'SYS · Quy trình & thông báo', '⌁', [
                $this->item('workflow', 'Workflow Center', 'admin.workflows.dashboard', ['admin.workflows.dashboard', 'admin.workflows.instances.*'], 'workflow.view', $company),
                $this->item('workflow-definitions', 'Workflow Definitions', 'admin.workflows.definitions', ['admin.workflows.definitions*'], 'workflow.view', $company),
                $this->item('workflow-tasks', 'Workflow Tasks', 'admin.workflows.tasks', ['admin.workflows.tasks*'], 'workflow.view', $company),
                $this->item('notifications', 'Notification Center', 'admin.notifications.index', ['admin.notifications.index', 'admin.notifications.show'], 'notification.view', $company),
                $this->item('notification-templates', 'Notification Templates', 'admin.notifications.templates.index', ['admin.notifications.templates.*'], 'notification.template.manage', $company),
                $this->item('notification-deliveries', 'Notification Deliveries', 'admin.notifications.deliveries.index', ['admin.notifications.deliveries.*'], 'notification.delivery.view', $company),
            ]),
            $this->group('access', 'IAM · Danh tính & kiểm soát', '◆', [
                $this->item('iam', 'IAM Governance', 'admin.iam.dashboard', ['admin.iam.dashboard', 'admin.iam.access-360'], 'iam.view', $company),
                $this->item('access-requests', 'Access Requests', 'admin.iam.requests.index', ['admin.iam.requests.*'], 'iam.view', $company),
                $this->item('roles-permissions', 'Roles & Permissions', 'admin.iam.roles', ['admin.iam.roles'], 'iam.view', $company),
                $this->item('privileged', 'Privileged Access', 'admin.iam.privileged', ['admin.iam.privileged'], 'iam.privileged.manage', $company),
                $this->item('segregation-of-duties', 'Segregation of Duties', 'admin.iam.sod', ['admin.iam.sod'], 'iam.sod.manage', $company),
                $this->item('access-reviews', 'Access Reviews', 'admin.iam.reviews', ['admin.iam.reviews*'], 'iam.review.manage', $company),
                $this->item('audit', 'Audit Platform', 'admin.audit.dashboard', ['admin.audit.*'], 'audit.view', $company),
            ]),
            $this->group('operations', 'ITSM · Vận hành nền tảng', '◉', [
                $this->item('integrations', 'Integrations', 'admin.integrations.dashboard', ['admin.integrations.*'], 'integration.view', $company),
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
            $this->group('seller', 'OMS · Bán hàng & đơn hàng', '▣', [
                $this->item('seller-dashboard', 'Tổng quan Seller', 'admin.seller.dashboard', ['admin.seller.dashboard'], 'commerce.store.view', $company),
                $this->item('store-profile', 'Hồ sơ cửa hàng', 'admin.seller.store', ['admin.seller.store*'], 'commerce.store.view', $company),
                $this->item('products', 'Sản phẩm', 'admin.products.index', ['admin.products.*'], 'product.view', $company),
                $this->item('branch-customers', 'Khách hàng', 'admin.branch-commerce.customers', ['admin.branch-commerce.customers*'], 'branch-commerce.view', $company),
                $this->item('sales-receivable', 'Sales Invoice · Receivable', 'admin.sales.index', ['admin.sales.*'], 'sales.view', $company, true),
                $this->item('seller-orders', 'Đơn hàng', 'admin.seller.orders.index', ['admin.seller.orders.*'], 'commerce.store.view', $company),
                $this->item('commerce-sales-history', 'Lịch sử đơn hàng đã bán', 'admin.commerce.sales-history', ['admin.commerce.sales-history'], 'commerce.payment.view', $company),
                $this->item('commerce-payments', 'Commerce Payments', 'admin.commerce.payments.index', ['admin.commerce.payments.*'], 'commerce.payment.view', $company),
                $this->item('product-categories', 'Danh mục sản phẩm', 'admin.product-categories.index', ['admin.product-categories.*'], 'product-category.view'),
                $this->item('seller-governance', 'Quản trị Seller', 'admin.commerce.sellers.index', ['admin.commerce.sellers.*'], 'commerce.seller.governance.view', $company),
            ]),
            $this->group('inventory', 'WMS · Kho & tồn kho', '⇅', [
                $this->item('seller-inventory', 'Tồn kho', 'admin.seller.inventory', ['admin.seller.inventory'], 'inventory.view', $company),
                $this->item('inventory-movements', 'Biến động kho', 'admin.seller.inventory.movements', ['admin.seller.inventory.movements'], 'inventory.view', $company),
                $this->item('inventory-governance', 'Kiểm kê & điều chỉnh', 'admin.seller.inventory.governance', ['admin.seller.inventory.governance*'], 'stock-count.view', $company),
            ]),
            $this->group('pos', 'POS · Bán hàng tại quầy', '▦', [
                $this->item('pos-dashboard', 'Tổng quan POS', 'admin.branch-commerce.dashboard', ['admin.branch-commerce.dashboard'], 'branch-commerce.view', $company),
                $this->item('customer-checkins', 'Thu ngân · Check-in / Checkout', 'admin.branch-commerce.checkins', ['admin.branch-commerce.checkins*', 'admin.branch-commerce.checkout*'], 'branch-commerce.view', $company),
                $this->item('company-services', 'Dịch vụ công ty', 'admin.branch-commerce.services', ['admin.branch-commerce.services*'], 'branch-commerce.view', $company),
                $this->item('branch-warehouse', 'Kho POS / Tồn kho chi nhánh', 'admin.branch-commerce.warehouse', ['admin.branch-commerce.warehouse'], 'branch-commerce.view', $company),
                $this->item('branch-sales', 'Phiếu thu · Doanh số POS', 'admin.branch-commerce.sales', ['admin.branch-commerce.sales'], 'branch-commerce.view', $company),
                $this->item('pos-receipts', 'Lịch sử phiếu thu', 'admin.branch-commerce.receipts', ['admin.branch-commerce.receipts*'], 'branch-commerce.view', $company),
            ]),
            $this->group('content', 'CMS · Nội dung website', '▧', [
                $this->item('articles', 'Kho nội dung', 'articles.index', ['articles.index', 'articles.edit'], 'article.view', $company),
                $this->item('article-create', 'Viết bài mới', 'articles.create', ['articles.create'], 'article.create', $company),
            ]),
        ];
        $order = ['overview', 'people', 'workforce', 'talent', 'payroll', 'crm', 'supply-chain', 'inventory', 'seller', 'pos', 'procurement', 'finance', 'tax-statutory', 'asset-maintenance', 'documents', 'analytics-planning', 'content', 'access', 'automation', 'operations'];
        $priority = array_flip($order);
        usort($groups, static fn (array $left, array $right): int => $priority[$left['key']] <=> $priority[$right['key']]);

        return $groups;
    }

    /** @param list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,process_help:bool,active:bool}> $items
     * @return array{key:string,label:string,icon:string,items:list<array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,process_help:bool,active:bool}>}
     */
    private function group(string $key, string $label, string $icon, array $items): array
    {
        return compact('key', 'label', 'icon', 'items');
    }

    /** @param list<string> $patterns
     * @param  array<string, int|string>  $parameters
     * @return array{key:string,label:string,route:string,parameters:array<string,int|string>,patterns:list<string>,permission:string,process_help:bool,active:bool}
     */
    private function item(string $key, string $label, string $route, array $patterns, string $permission, array $parameters = [], bool $processHelp = false): array
    {
        return compact('key', 'label', 'route', 'parameters', 'patterns', 'permission') + ['process_help' => $processHelp, 'active' => false];
    }
}
