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
            $this->group('overview', 'Tổng quan', '◫', [
                $this->item('dashboard', 'Dashboard', 'dashboard', ['dashboard'], 'dashboard.view'),
            ]),
            $this->group('organization', 'Tổ chức', '⌂', [
                $this->item('companies', 'Công ty', 'admin.companies.index', ['admin.companies.*'], 'company.view'),
                $this->item('cost-centers', 'Trung tâm chi phí', 'admin.cost-centers.index', ['admin.cost-centers.*'], 'cost-center.view', $company),
                $this->item('organization-units', 'Đơn vị tổ chức', 'admin.organization-units.index', ['admin.organization-units.index', 'admin.organization-units.show', 'admin.organization-units.edit'], 'organization-unit.view', $company),
                $this->item('organization-tree', 'Cây tổ chức', 'admin.organization-units.tree', ['admin.organization-units.tree'], 'organization-unit.view', $company),
                $this->item('job-positions', 'Vị trí công việc', 'admin.job-positions.index', ['admin.job-positions.*'], 'job-position.view', $company),
                $this->item('headcount', 'Báo cáo định biên', 'admin.organization.headcount', ['admin.organization.headcount'], 'organization-unit.view', $company),
            ]),
            $this->group('people', 'Nhân sự cốt lõi', '♙', [
                $this->item('employees', 'Nhân viên', 'admin.employees.index', ['admin.employees.*'], 'employee.view', $company),
                $this->item('employee-lifecycle', 'Vòng đời nhân sự', 'admin.employee-lifecycle.index', ['admin.employee-lifecycle.*'], 'hr.lifecycle.view', $company),
                $this->item('attendance', 'Chấm công', 'admin.attendance.index', ['admin.attendance.*'], 'attendance.view', $company),
                $this->item('leave', 'Nghỉ phép', 'admin.leave.index', ['admin.leave.*'], 'leave.view', $company),
                $this->item('shifts', 'Ca và lịch làm việc', 'admin.workforce.management', ['admin.workforce.*'], 'shift.view', ['section' => 'shifts', ...$company]),
                $this->item('onboarding', 'Onboarding', 'admin.onboarding.dashboard', ['admin.onboarding.*', 'admin.onboarding-cases.*'], 'onboarding.view', $company),
            ]),
            $this->group('talent', 'Tuyển dụng và tài năng', '◎', [
                $this->item('recruitment', 'Tuyển dụng', 'admin.recruitment.dashboard', ['admin.recruitment.*', 'admin.recruitment-requisitions.*', 'admin.candidates.*', 'admin.interviews.*', 'admin.candidate-offers.*', 'admin.applications.*'], 'recruitment-requisition.view', $company),
                $this->item('performance', 'Hiệu suất và tài năng', 'admin.talent.performance.index', ['admin.talent.performance.*'], 'talent.performance.view', $company),
                $this->item('career', 'Career và kế nhiệm', 'admin.career.index', ['admin.career.*'], 'career.view', $company),
                $this->item('analytics', 'Workforce Analytics', 'admin.analytics.workforce', ['admin.analytics.*'], 'analytics.view', $company),
                $this->item('hr-governance', 'HR Data Governance', 'admin.hr.governance.dashboard', ['admin.hr.governance.*'], 'hr.governance.view', $company),
            ]),
            $this->group('payroll', 'Payroll và tài chính', '◇', [
                $this->item('payroll-periods', 'Kỳ lương', 'admin.payroll.periods.index', ['admin.payroll.periods.*'], 'payroll-period.view', $company),
                $this->item('payroll-inputs', 'Payroll Inputs', 'admin.payroll.inputs.index', ['admin.payroll.inputs.*', 'admin.payroll-period.*'], 'payroll-period.view', $company),
                $this->item('payroll-runs', 'Payroll Runs', 'admin.payroll.runs.index', ['admin.payroll.runs.*'], 'payroll.run.view', $company),
                $this->item('payroll-adjustments', 'Điều chỉnh lương', 'admin.payroll.adjustments.index', ['admin.payroll.adjustments.*'], 'payroll.adjustment.view', $company),
                $this->item('annual-tax', 'Thuế TNCN năm', 'admin.payroll.annual-tax.index', ['admin.payroll.annual-tax.*'], 'payroll.annual_tax.view', $company),
                $this->item('payslips', 'Phiếu lương', 'admin.payroll.payslips.index', ['admin.payroll.payslips.*'], 'payroll.payslip.view', $company),
                $this->item('payments', 'Thanh toán', 'admin.payroll.payment-batches.index', ['admin.payroll.payment-accounts.*', 'admin.payroll.payment-batches.*'], 'payroll.payment.view', $company),
                $this->item('accounting', 'Kế toán và bút toán', 'admin.payroll.accounting.journals.index', ['admin.payroll.accounting.*'], 'payroll.accounting.view', $company),
                $this->item('erp', 'ERP Export', 'admin.payroll.erp.exports.index', ['admin.payroll.erp.*'], 'erp.integration.view', $company),
                $this->item('government', 'Government Filing', 'admin.payroll.government-filings.index', ['admin.payroll.government-filings.*'], 'government.filing.view', $company),
            ]),
            $this->group('automation', 'Tự động hóa doanh nghiệp', '⌁', [
                $this->item('workflow', 'Workflow Center', 'admin.workflows.dashboard', ['admin.workflows.*'], 'workflow.view', $company),
                $this->item('notifications', 'Notification Center', 'admin.notifications.index', ['admin.notifications.*'], 'notification.view', $company),
                $this->item('integrations', 'Integration Hub', 'admin.integrations.dashboard', ['admin.integrations.*'], 'integration.view', $company),
                $this->item('audit', 'Audit Platform', 'admin.audit.dashboard', ['admin.audit.*'], 'audit.view', $company),
            ]),
            $this->group('access', 'Truy cập và bảo mật', '◆', [
                $this->item('iam', 'IAM Governance', 'admin.iam.dashboard', ['admin.iam.dashboard', 'admin.iam.requests.*', 'admin.iam.roles', 'admin.iam.access-360'], 'iam.view', $company),
                $this->item('privileged', 'Privileged Access', 'admin.iam.privileged', ['admin.iam.privileged', 'admin.iam.sod'], 'iam.view', $company),
                $this->item('access-reviews', 'Access Reviews', 'admin.iam.reviews', ['admin.iam.reviews*'], 'iam.view', $company),
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
                $this->item('continuity', 'Business Continuity', 'admin.operations.continuity', ['admin.operations.continuity'], 'operations.view'),
                $this->item('security-assurance', 'Security Assurance', 'admin.operations.security-assurance', ['admin.operations.security-assurance'], 'operations.view'),
                $this->item('api-documentation', 'API Documentation', 'l5-swagger.default.api', ['l5-swagger.default.api'], 'api.documentation.view'),
            ]),
            $this->group('content', 'Nội dung', '▤', [
                $this->item('articles', 'Kho nội dung', 'articles.index', ['articles.index', 'articles.edit'], 'article.view'),
                $this->item('article-create', 'Viết bài mới', 'articles.create', ['articles.create'], 'article.create'),
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
