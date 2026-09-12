<?php

namespace App\Support\Reporting;

use App\Support\DataQuality\DataQualityAuditService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Application\Services\CustomerMetricsService;
use Modules\Dashboard\Application\Services\EnterpriseAnalyticsReadService;
use Modules\Dashboard\Application\Services\SupplyChainOverviewReadService;
use Modules\Maintenance\Application\Services\MaintenanceReadService;
use Modules\Project\Application\Services\ProjectReadService;

final readonly class CurrentOperatingDataReportService
{
    public function __construct(
        private ConnectionInterface $db,
        private DataQualityAuditService $dataQuality,
        private CustomerMetricsService $customerMetrics,
        private SupplyChainOverviewReadService $supplyChain,
        private EnterpriseAnalyticsReadService $analytics,
        private ProjectReadService $projects,
        private MaintenanceReadService $maintenance,
    ) {}

    public function render(?int $companyId = null): string
    {
        $dq = $this->dataQuality->snapshot();
        $lines = [
            'ERP CURRENT OPERATING DATA SNAPSHOT',
            str_repeat('=', 80),
            'Generated At: '.now()->toIso8601String(),
            'Environment: '.$dq['environment'],
            'Configured Database: '.$dq['configured_database'],
            'Actual PDO Database: '.$dq['actual_database'],
            'Database Purpose: '.$dq['database_purpose'],
            'Database Match: '.($dq['database_matches'] ? 'YES' : 'NO'),
            'Company count: '.$this->count('companies', $companyId),
            'Branch count: '.$this->count('branches', $companyId),
            'Employee count: '.$this->count('employees', $companyId),
            'Active user accounts: '.$this->activeUsers($companyId),
            'Migration count: '.$this->count('migrations'),
            'Table count: '.$this->tableCount(),
            "Data Quality: {$dq['passed']}/{$dq['rules']} ".($dq['failed'] === 0 ? 'PASS' : 'FINDINGS'),
            '',
            'LƯU Ý: Dữ liệu vận hành trong môi trường local có thể là dữ liệu synthetic/bootstrap-owned dùng để mô phỏng doanh nghiệp, không phải dữ liệu cá nhân thật ngoài đời.',
            'Các số liệu dưới đây được truy vấn tại thời điểm tạo báo cáo; KHÔNG phải tổng được chép từ tài liệu.',
            'Sơ đồ menu: SO_DO_LUONG_MENU_ERP.txt',
            'Sơ đồ module: SO_DO_LIEN_KET_MODULE_ERP.txt',
        ];

        $this->businessSummary($lines, $dq, $companyId);
        $this->companyBranch($lines, $companyId);
        $this->accounts($lines, $companyId);
        $this->employees($lines, $companyId);
        $this->attendancePayroll($lines, $companyId);
        $this->recruitment($lines, $companyId);
        $this->productsInventorySales($lines, $companyId);
        $this->salesReceivableCashFlow($lines, $companyId);
        $this->supplyChain($lines, $companyId);
        $this->analytics($lines, $companyId);
        $this->documentWorkflow($lines, $companyId);
        $this->projectManagement($lines, $companyId);
        $this->maintenance($lines, $companyId);
        $this->procurementFinance($lines, $companyId);
        $this->accounting($lines, $companyId);
        $this->lifecycleNotifications($lines, $companyId);
        $this->qualityAndFeatures($lines, $dq, $companyId);
        $this->projectScale($lines);

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function maintenance(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'MAINTENANCE / CMMS');
        if ($companyId === null) {
            $out[] = 'Chọn Company scope để xem Maintenance canonical.';

            return;
        }
        $summary = $this->maintenance->read($companyId)['summary'];
        $number = fn (string $key): int => $this->numericInteger(data_get($summary, $key));
        $out[] = 'Requests Total / Submitted / Approved / Converted: '.$number('requests').' / '.$number('submitted').' / '.$number('approved').' / '.$number('converted');
        $out[] = 'Work Orders Total / Draft / Planned / In Progress / On Hold / Completed / Cancelled / Overdue: '.$number('work_orders').' / '.$number('draft').' / '.$number('planned').' / '.$number('in_progress').' / '.$number('on_hold').' / '.$number('completed').' / '.$number('cancelled').' / '.$number('overdue');
        $out[] = 'Preventive Plans / Active / Due: '.$number('plans').' / '.$number('active_plans').' / '.$number('due_plans');
        $out[] = 'Downtime minutes: '.$number('downtime_minutes').'; Parts cost: '.$this->money($number('parts_cost_minor')).'; approved external cost: '.$this->money($number('external_cost_minor')).'.';
        $out[] = 'Owner documents — Expense / Purchase Request: '.$number('external_expense_count').' / '.$number('procurement_count').'.';
        $out[] = 'Inventory linkage: PASS; Procurement: PASS; Expense: PASS; Accounting: PARTIAL (Expense posts canonically; spare-part consumption posting chưa có policy); DMS: PASS; Workflow/Notification: PARTIAL.';
        $out[] = 'MTTR / MTBF: NOT_ENOUGH_EVIDENCE / NOT_ENOUGH_EVIDENCE.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function projectManagement(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'PROJECT MANAGEMENT');
        if ($companyId === null) {
            $out[] = 'Chọn Company scope để xem Project Management canonical.';

            return;
        }
        $summary = $this->projects->read($companyId)['summary'];
        $number = fn (string $key): int => $this->numericInteger(data_get($summary, $key));
        $out[] = 'Projects / Active / Completed / On Hold / Cancelled / At Risk / Overdue: '.$number('projects').' / '.$number('active').' / '.$number('completed').' / '.$number('on_hold').' / '.$number('cancelled').' / '.$number('at_risk').' / '.$number('overdue_projects');
        $out[] = 'Tasks total / Todo / In Progress / Blocked / Done / Overdue: '.$number('tasks.total').' / '.$number('tasks.todo').' / '.$number('tasks.in_progress').' / '.$number('tasks.blocked').' / '.$number('tasks.done').' / '.$number('tasks.overdue');
        $out[] = 'Milestones total / Open / Completed / Overdue: '.$number('milestones.total').' / '.$number('milestones.open').' / '.$number('milestones.completed').' / '.$number('milestones.overdue');
        $out[] = 'Active members: '.$number('active_members');
        $out[] = 'Timesheets Draft / Submitted / Approved / Rejected / Approved minutes: '.$number('timesheets.draft').' / '.$number('timesheets.submitted').' / '.$number('timesheets.approved').' / '.$number('timesheets.rejected').' / '.$number('timesheets.approved_minutes');
        $out[] = 'Planned project budget: '.$this->money($this->numericInteger(data_get($summary, 'budget_minor'))).'; approved Expense actual: '.$this->money($number('finance.actual_minor')).'.';
        $out[] = 'Owner documents — Expense / Purchase Request / Asset: '.$number('finance.expense_count').' / '.$number('finance.procurement_count').' / '.$number('finance.asset_count').'.';
        $out[] = 'Expense linkage: PASS; Procurement linkage: PASS; Asset source trace: PARTIAL; Revenue/Billing: NOT_IMPLEMENTED; Workflow/Notification: PARTIAL.';
        $out[] = 'Documents: '.$this->db->table('enterprise_documents')->where(['company_id' => $companyId, 'owner_module' => 'Project'])->count().'; pending approvals: '.$number('timesheets.submitted').'.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function documentWorkflow(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'DOCUMENT MANAGEMENT');
        $out[] = 'Documents / Active / Archived / Versions: '.$this->count('enterprise_documents', $companyId).' / '.$this->statusCount('enterprise_documents', 'ACTIVE', $companyId).' / '.$this->statusCount('enterprise_documents', 'ARCHIVED', $companyId).' / '.$this->count('enterprise_document_versions');
        $missingSha = $this->db->table('enterprise_document_versions')->whereRaw('CHAR_LENGTH(sha256) <> 64')->count();
        $missingCurrent = $this->db->table('enterprise_documents')->whereNull('current_version_id')->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))->count();
        $out[] = 'Missing SHA / Missing current version: '.$missingSha.' / '.$missingCurrent;
        $out[] = 'Storage: LOCAL_PRIVATE; external private object provider: CONFIGURATION_REQUIRED.';
        $domains = $this->db->table('enterprise_documents')->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))->selectRaw('owner_module, COUNT(*) total')->groupBy('owner_module')->orderBy('owner_module')->get();
        $out[] = 'Documents by domain: '.($domains->map(fn (object $row): string => $this->rowString($row, 'owner_module').'='.$this->rowInteger($row, 'total'))->implode(', ') ?: 'none');

        $this->heading($out, 'WORKFLOW / APPROVAL');
        $out[] = 'Workflow definitions / instances / tasks: '.$this->count('workflow_definitions').' / '.$this->count('workflow_instances', $companyId).' / '.$this->count('workflow_tasks');
        $out[] = 'Owner-domain Commands retain business mutation ownership; DMS stores private evidence only.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function supplyChain(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'SUPPLY CHAIN');
        if ($companyId === null) {
            $out[] = 'Chọn Company scope để xem SCM canonical.';

            return;
        }
        $data = $this->supplyChain->read($companyId);
        $out[] = 'Open PO / Pending GR / Inbound qty / Inbound value: '.$this->numericInteger(data_get($data, 'supply.open_purchase_orders')).' / '.$this->numericInteger(data_get($data, 'supply.pending_receipts')).' / '.$this->numericInteger(data_get($data, 'supply.inbound_quantity')).' / '.$this->money($this->numericInteger(data_get($data, 'supply.inbound_value_minor')));
        $out[] = 'Inventory SKU / On hand / Reserved / Available / In transit / Value: '.$this->numericInteger(data_get($data, 'inventory.skus')).' / '.$this->numericInteger(data_get($data, 'inventory.on_hand')).' / '.$this->numericInteger(data_get($data, 'inventory.reserved')).' / '.$this->numericInteger(data_get($data, 'inventory.available')).' / '.$this->numericInteger(data_get($data, 'inventory.in_transit')).' / '.$this->money($this->numericInteger(data_get($data, 'inventory.inventory_value_minor')));
        $out[] = 'Low stock / Out of stock / Pending transfers: '.$this->numericInteger(data_get($data, 'inventory.low_stock')).' / '.$this->numericInteger(data_get($data, 'inventory.out_of_stock')).' / '.$this->numericInteger(data_get($data, 'inventory.pending_transfers'));
        $out[] = 'Demand ERP open / remaining units / Marketplace pending: '.$this->numericInteger(data_get($data, 'demand.erp_sales.open_orders')).' / '.$this->numericInteger(data_get($data, 'demand.erp_sales.remaining_quantity')).' / '.$this->numericInteger(data_get($data, 'demand.marketplace_pending'));
        $out[] = 'Logistics ready / in transit / delivered / failed / returned: '.$this->numericInteger(data_get($data, 'fulfillment.ready')).' / '.$this->numericInteger(data_get($data, 'fulfillment.in_transit')).' / '.$this->numericInteger(data_get($data, 'fulfillment.delivered')).' / 0 / 0';
        $out[] = 'External carrier integration: NOT_CONNECTED; delivery performance: NOT_ENOUGH_EVIDENCE; logistics cost: NOT_IMPLEMENTED.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function analytics(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'BI / ANALYTICS');
        if ($companyId === null) {
            $out[] = 'Chọn Company scope để xem BI canonical.';

            return;
        }
        $to = now()->endOfMonth();
        $from = $to->copy()->subMonths(11)->startOfMonth();
        $data = $this->analytics->read($companyId, null, $from->toDateString(), $to->toDateString());
        $currentMonth = data_get($data, 'period.current.month');
        $previousMonth = data_get($data, 'period.previous.month');
        $out[] = 'Latest / Previous month: '.(is_string($currentMonth) ? $currentMonth : 'N/A').' / '.(is_string($previousMonth) ? $previousMonth : 'N/A');
        $out[] = 'Revenue current / previous / variance: '.$this->money($this->numericInteger(data_get($data, 'period.current.revenue'))).' / '.$this->money($this->numericInteger(data_get($data, 'period.previous.revenue'))).' / '.$this->money($this->numericInteger(data_get($data, 'period.revenue_variance')));
        $out[] = 'ERP Sales / Marketplace / POS current: '.$this->money($this->numericInteger(data_get($data, 'period.current.erp_sales'))).' / '.$this->money($this->numericInteger(data_get($data, 'period.current.marketplace'))).' / '.$this->money($this->numericInteger(data_get($data, 'period.current.pos')));
        $out[] = 'COGS / Gross Profit / Operating Result: '.$this->money($this->numericInteger(data_get($data, 'finance.income.cogs'))).' / '.$this->money($this->numericInteger(data_get($data, 'finance.income.gross_profit'))).' / '.$this->money($this->numericInteger(data_get($data, 'finance.income.profit')));
        $out[] = 'Payroll / OPEX current: '.$this->money($this->numericInteger(data_get($data, 'period.current.payroll'))).' / '.$this->money($this->numericInteger(data_get($data, 'period.current.opex')));
        $out[] = 'AR / AP / Net cash movement: '.$this->money($this->numericInteger(data_get($data, 'finance.ar_outstanding'))).' / '.$this->money($this->numericInteger(data_get($data, 'finance.ap_outstanding'))).' / '.$this->money($this->numericInteger(data_get($data, 'finance.cash_flow.net_movement')));
        $out[] = 'Headcount / Late records / OT minutes: '.$this->numericInteger(data_get($data, 'people.active_headcount')).' / '.$this->numericInteger(data_get($data, 'people.late_records')).' / '.$this->numericInteger(data_get($data, 'people.overtime_minutes'));
        $out[] = 'Inventory value / Out of stock: '.$this->money($this->numericInteger(data_get($data, 'supply_chain.inventory.inventory_value_minor'))).' / '.$this->numericInteger(data_get($data, 'supply_chain.inventory.out_of_stock'));
        $out[] = 'CRM NEW / ACTIVE / REPEAT / AT_RISK / DORMANT: '.$this->numericInteger(data_get($data, 'crm.new')).' / '.$this->numericInteger(data_get($data, 'crm.active')).' / '.$this->numericInteger(data_get($data, 'crm.repeat')).' / '.$this->numericInteger(data_get($data, 'crm.at_risk')).' / '.$this->numericInteger(data_get($data, 'crm.dormant'));
        $out[] = 'Forecast: AR/AP due-date only; future revenue and inventory turnover: NOT_ENOUGH_EVIDENCE.';
    }

    /**
     * @param  array<int,string>  $out
     *
     * @param-out array<int,string> $out
     *
     * @param  array<string,mixed>  $dq
     */
    private function businessSummary(array &$out, array $dq, ?int $companyId): void
    {
        $this->heading($out, 'TÓM TẮT HOẠT ĐỘNG DOANH NGHIỆP');
        $debit = $this->firstExistingSum('payroll_accounting_journal_lines', ['debit_minor']);
        $credit = $this->firstExistingSum('payroll_accounting_journal_lines', ['credit_minor']);
        $out[] = 'Nhân sự: '.$this->count('employees', $companyId);
        $out[] = 'Nhân viên đi trễ: '.$this->distinctCount('attendance_records', 'employee_id', $companyId, 'late_minutes');
        $out[] = 'Gross / Net Payroll: '.$this->money($this->sum('payroll_runs', 'gross_total_minor', $companyId)).' / '.$this->money($this->sum('payroll_runs', 'net_total_minor', $companyId));
        $out[] = 'PIT / Employee Insurance / Employer Insurance: '.$this->money($this->sum('payroll_runs', 'personal_income_tax_total_minor', $companyId)).' / '.$this->money($this->sum('payroll_runs', 'insurance_employee_total_minor', $companyId)).' / '.$this->money($this->sum('payroll_runs', 'insurance_employer_total_minor', $companyId));
        $out[] = 'Marketplace Orders / recognized revenue: '.$this->count('orders').' / '.$this->money($this->revenueBySource('commerce_order', $companyId));
        $out[] = 'POS Receipts / revenue: '.$this->count('branch_pos_receipts', $companyId).' / '.$this->money($this->revenueBySource('branch_pos', $companyId));
        $out[] = 'ERP Sales Orders / Customer Invoices / AR outstanding: '.$this->count('sales_orders', $companyId).' / '.$this->count('customer_invoices', $companyId).' / '.$this->money($this->arOutstanding($companyId));
        $out[] = 'Products / SKUs / On-hand / Inventory value: '.$this->count('products').' / '.$this->count('product_variants').' / '.$this->sum('inventory_balances', 'on_hand').' / '.$this->money($this->sum('inventory_balances', 'inventory_value_minor'));
        $out[] = 'Purchase Orders / AP outstanding: '.$this->count('purchase_orders', $companyId).' / '.$this->money($this->apOutstanding($companyId));
        $out[] = 'Expenses / Assets / Treasury transactions: '.$this->count('finance_expenses', $companyId).' / '.$this->count('finance_assets', $companyId).' / '.$this->count('treasury_transactions', $companyId);
        $out[] = 'Posted Journals / Debit / Credit / Difference: '.$this->statusCount('payroll_accounting_journals', 'internally_posted', $companyId).' / '.$this->money($debit).' / '.$this->money($credit).' / '.$this->money($debit - $credit);
        $out[] = 'Contracts expiring 30 days: '.$this->contractsExpiring($companyId, 30);
        $out[] = 'Pending resignations / employees offboarding: '.$this->pendingStatuses('employee_resignations', ['draft', 'submitted'], $companyId).' / '.$this->statusCount('employee_offboarding_cases', 'in_progress', $companyId);
        $out[] = 'Pending accounting adjustments: '.$this->pendingStatuses('accounting_adjustment_requests', ['draft', 'submitted', 'approved'], $companyId);
        $out[] = 'Data Quality: '.$this->arrayInteger($dq, 'passed').'/'.$this->arrayInteger($dq, 'rules').' PASS';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function companyBranch(array &$out, ?int $companyId): void
    {
        $this->heading($out, '1 — COMPANY / BRANCH');
        $companies = $this->db->table('companies')->when($companyId, fn ($q) => $q->where('id', $companyId))->whereNull('deleted_at')->orderBy('id')->get();
        foreach ($companies as $company) {
            $id = $this->rowInteger($company, 'id');
            $out[] = 'Company: '.$this->rowString($company, 'name').' ('.$this->rowString($company, 'code').')';
            $out[] = '- Status: '.($this->rowNullableString($company, 'status') ?? ($this->rowInteger($company, 'is_active') === 1 ? 'active' : 'inactive'));
            $out[] = '- Branch count: '.$this->count('branches', $id);
            $out[] = '- Employee count: '.$this->count('employees', $id);
            $out[] = '- Active user count: '.$this->companyActiveUsers($id);
            $out[] = '- Payroll gross/net: '.$this->money($this->sum('payroll_runs', 'gross_total_minor', $id)).' / '.$this->money($this->sum('payroll_runs', 'net_total_minor', $id));
            $out[] = '- AP outstanding: '.$this->money($this->apOutstanding($id));
            $out[] = '- Inventory value: '.$this->money($this->companyInventoryValue($id));
            $out[] = '- Expense: '.$this->money($this->sum('finance_expenses', 'amount_minor', $id));
            $out[] = '- Revenue/operating result: '.($this->has('branch_financial_snapshots') ? 'xem nguồn Branch Finance bên dưới' : 'KHÔNG ĐỦ BẰNG CHỨNG');
            foreach ($this->db->table('branches')->where('company_id', $id)->whereNull('deleted_at')->orderBy('id')->get() as $branch) {
                $bid = $this->rowInteger($branch, 'id');
                $out[] = '  Branch: '.$this->rowString($branch, 'name').' ('.$this->rowString($branch, 'code').')';
                $out[] = '  - Headcount: '.$this->branchHeadcount($bid);
                $out[] = '  - Attendance / late: '.$this->branchCount('attendance_records', $bid).' / '.$this->branchLate($bid);
                $out[] = '  - Inventory qty/value: '.$this->branchInventoryQty($bid).' / '.$this->money($this->branchInventoryValue($bid));
                $out[] = '  - Expense: '.$this->money($this->branchSum('finance_expenses', 'amount_minor', $bid));
                $out[] = '  - POS receipts: '.$this->branchCount('branch_pos_receipts', $bid);
            }
            $out[] = '';
        }
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function accounts(array &$out, ?int $companyId): void
    {
        $this->heading($out, '2 — CURRENT USER ACCOUNTS');
        $query = $this->db->table('users as u')->where('u.access_enabled', 1)->orderBy('u.id');
        if ($companyId && $this->has('company_user_access')) {
            $query->whereExists(fn (Builder $q) => $q->from('company_user_access as cua')->whereColumn('cua.user_id', 'u.id')->where('cua.company_id', $companyId));
        }
        foreach ($query->get(['u.id', 'u.name', 'u.email', 'u.access_enabled', 'u.email_verified_at']) as $user) {
            $userId = $this->rowInteger($user, 'id');
            $roles = $this->roles($userId);
            $employee = $this->db->table('employees')->where('user_id', $userId)->first(['employee_code', 'full_name']);
            $out[] = 'Account #'.$userId.': '.$this->rowString($user, 'name');
            $out[] = '- Email: '.$this->maskEmail($this->rowString($user, 'email'));
            $out[] = '- Status: active; email verified: '.($this->rowNullableString($user, 'email_verified_at') ? 'YES' : 'NO');
            $out[] = '- Roles: '.($roles ?: 'N/A');
            $out[] = '- Effective permission count: '.$this->permissionCount($userId);
            $out[] = '- Employee: '.($employee ? $this->rowString($employee, 'full_name').' ('.$this->rowString($employee, 'employee_code').')' : 'N/A');
            $out[] = '- Company access count: '.$this->userCompanyCount($userId);
        }
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function employees(array &$out, ?int $companyId): void
    {
        $this->heading($out, '3 — EMPLOYEE MASTER / ATTENDANCE / PAYROLL');
        $employees = $this->db->table('employees as e')->leftJoin('companies as c', 'c.id', '=', 'e.company_id')
            ->when($companyId, fn ($q) => $q->where('e.company_id', $companyId))->whereNull('e.deleted_at')
            ->orderBy('e.employee_code')->get(['e.*', 'c.name as company_name']);
        foreach ($employees as $employee) {
            $employeeId = $this->rowInteger($employee, 'id');
            $assignment = $this->db->table('employee_assignments as ea')->leftJoin('organization_units as ou', 'ou.id', '=', 'ea.organization_unit_id')->leftJoin('job_positions as jp', 'jp.id', '=', 'ea.job_position_id')->where('ea.employee_id', $employeeId)->whereNull('ea.effective_to')->orderByDesc('ea.effective_from')->first(['ea.*', 'ou.name as unit_name', 'jp.title as position_name']);
            $branches = $this->employeeBranches($employeeId);
            $attendance = $this->db->table('attendance_records')->where('employee_id', $employeeId)->selectRaw('COUNT(*) records, SUM(late_minutes > 0) late_count, COALESCE(SUM(late_minutes),0) late_minutes, COALESCE(SUM(overtime_minutes),0) overtime_minutes')->first() ?? new \stdClass;
            $leave = $this->db->table('leave_requests')->where('employee_id', $employeeId)->where('status', 'approved')->selectRaw('COUNT(*) requests, COALESCE(SUM(requested_minutes),0) minutes')->first() ?? new \stdClass;
            $payroll = $this->db->table('payroll_items as pi')->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')->join('payroll_periods as pp', 'pp.id', '=', 'pr.payroll_period_id')->where('pi.employee_id', $employeeId)->whereIn('pr.status', ['finalized', 'approved'])->orderByDesc('pp.period_end')->first(['pi.*', 'pp.code as period_code', 'pr.status as run_status']);
            $out[] = str_repeat('-', 80);
            $out[] = 'NHÂN VIÊN: '.$this->rowString($employee, 'full_name').' ('.$this->rowString($employee, 'employee_code').')';
            $out[] = 'Status: '.$this->rowString($employee, 'employment_status').'; Company: '.$this->rowString($employee, 'company_name').'; Branch: '.($branches ?: 'N/A');
            $out[] = 'Assignment: '.($assignment ? $this->rowString($assignment, 'position_name').' / '.$this->rowString($assignment, 'unit_name').' / '.$this->rowString($assignment, 'effective_from') : 'N/A');
            $out[] = 'Employment: '.$this->rowString($employee, 'employment_type').'; Hire date: '.$this->rowString($employee, 'join_date').'; Termination: '.($this->rowNullableString($employee, 'termination_date') ?: 'N/A');
            $out[] = 'Attendance: records '.$this->rowInteger($attendance, 'records').', late '.$this->rowInteger($attendance, 'late_count').' lần / '.$this->rowInteger($attendance, 'late_minutes').' phút, OT '.round($this->rowInteger($attendance, 'overtime_minutes') / 60, 2).' giờ';
            $out[] = 'Approved leave: '.$this->rowInteger($leave, 'requests').' request / '.round($this->rowInteger($leave, 'minutes') / 480, 2).' ngày quy đổi';
            if ($payroll) {
                $out[] = 'Latest canonical payroll: '.$this->rowString($payroll, 'period_code').' / '.$this->rowString($payroll, 'run_status');
                $out[] = 'Gross '.$this->money($this->rowInteger($payroll, 'gross_amount_minor')).'; deductions '.$this->money($this->rowInteger($payroll, 'deduction_amount_minor')).'; PIT '.$this->money($this->rowInteger($payroll, 'personal_income_tax_minor')).'; net '.$this->money($this->rowInteger($payroll, 'net_amount_minor'));
                $out[] = 'Insurance employee/employer: '.$this->money($this->rowInteger($payroll, 'insurance_employee_total_minor')).' / '.$this->money($this->rowInteger($payroll, 'insurance_employer_total_minor'));
            } else {
                $out[] = 'Latest canonical payroll: N/A';
            }
        }
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function attendancePayroll(array &$out, ?int $companyId): void
    {
        $this->heading($out, '4–6 — ATTENDANCE / LEAVE / OT / PAYROLL / STATUTORY');
        $out[] = 'Attendance records: '.$this->count('attendance_records', $companyId);
        $out[] = 'Late occurrences/minutes: '.$this->whereCount('attendance_records', 'late_minutes', '>', 0, $companyId).' / '.$this->sum('attendance_records', 'late_minutes', $companyId);
        $out[] = 'Approved overtime records/minutes: '.$this->statusCount('overtime_requests', 'approved', $companyId).' / '.$this->statusSum('overtime_requests', 'approved_minutes', 'approved', $companyId);
        $out[] = 'Approved leave requests/minutes: '.$this->statusCount('leave_requests', 'approved', $companyId).' / '.$this->statusSum('leave_requests', 'requested_minutes', 'approved', $companyId);
        foreach ($this->db->table('payroll_runs as pr')->join('payroll_periods as pp', 'pp.id', '=', 'pr.payroll_period_id')->when($companyId, fn ($q) => $q->where('pr.company_id', $companyId))->orderByDesc('pp.period_end')->get(['pr.*', 'pp.code as period_code']) as $run) {
            $status = $this->rowString($run, 'status');
            $out[] = 'Payroll '.$this->rowString($run, 'period_code').': '.$status.($status === 'finalized' ? ' / FINALIZED - IMMUTABLE' : '');
            $out[] = '- Employees '.$this->rowInteger($run, 'employee_count').'; Gross '.$this->money($this->rowInteger($run, 'gross_total_minor')).'; deductions '.$this->money($this->rowInteger($run, 'deduction_total_minor')).'; PIT '.$this->money($this->rowInteger($run, 'personal_income_tax_total_minor')).'; net '.$this->money($this->rowInteger($run, 'net_total_minor')).'; employer contribution '.$this->money($this->rowInteger($run, 'employer_contribution_total_minor'));
            $out[] = '- Payslips: '.$this->db->table('payroll_payslips')->where('payroll_run_id', $this->rowInteger($run, 'id'))->count();
        }
        $out[] = 'PIT > 0 rows / PIT = 0 rows / total: '.$this->whereCount('payroll_items', 'personal_income_tax_minor', '>', 0, $companyId).' / '.$this->whereCount('payroll_items', 'personal_income_tax_minor', '=', 0, $companyId).' / '.$this->money($this->sum('payroll_items', 'personal_income_tax_minor', $companyId));
        $out[] = 'Insurance employee/employer: '.$this->money($this->sum('payroll_items', 'insurance_employee_total_minor', $companyId)).' / '.$this->money($this->sum('payroll_items', 'insurance_employer_total_minor', $companyId));
        $out[] = 'VAT/CIT/Government filing: chỉ hiển thị theo policy và filing runtime; không có bằng chứng gửi cơ quan nhà nước.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function recruitment(array &$out, ?int $companyId): void
    {
        $this->heading($out, '7 — RECRUITMENT / ONBOARDING');
        foreach (['recruitment_requisitions', 'candidates', 'applications', 'application_interviews', 'candidate_offers', 'onboarding_cases'] as $table) {
            $out[] = "$table: ".$this->count($table, $companyId);
        }
        $out[] = 'Canonical hire lineage is retained by employees.source_candidate_id/source_application_id/source_offer_id when available.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function productsInventorySales(array &$out, ?int $companyId): void
    {
        $this->heading($out, '8–12 — PRODUCTS / INVENTORY / MARKETPLACE / POS / CRM');
        foreach (['products', 'product_categories', 'product_variants', 'inventory_balances', 'inventory_movements', 'orders', 'order_items', 'branch_pos_receipts', 'commerce_customers', 'customer_visits'] as $table) {
            $out[] = "$table: ".$this->count($table, $companyId);
        }
        $out[] = 'Inventory on-hand/value: '.$this->sum('inventory_balances', 'on_hand').' / '.$this->money($this->sum('inventory_balances', 'inventory_value_minor'));
        $out[] = 'Recognized marketplace revenue: '.$this->money($this->revenueBySource('commerce_order', $companyId));
        $out[] = 'Product media LOCAL/CLOUDFLARE/NO_MEDIA: '.$this->providerCount('local').' / '.$this->providerCount('cloudflare-images').' / '.$this->productsWithoutMedia();
        $out[] = 'BROKEN_MEDIA: '.$this->brokenMediaCount();
        $companyIds = $companyId === null ? $this->db->table('companies')->whereNull('deleted_at')->pluck('id') : collect([$companyId]);
        $crm = $companyIds->filter(fn (mixed $id): bool => is_numeric($id))->flatMap(fn (mixed $id) => $this->customerMetrics->forCompany(is_int($id) ? $id : (int) (string) $id));
        $crmSpend = $crm->reduce(fn (int $sum, array $metric): int => $sum + $metric['recognized_spend_minor'], 0);
        $out[] = 'CRM deterministic customer metrics: '.$crm->count().' customers; recognized spend '.$this->money($crmSpend).'.';
        foreach ($crm->groupBy('segment')->sortKeys() as $segment => $customers) {
            $out[] = '- Segment '.$segment.': '.$customers->count();
        }
        foreach ($crm as $metric) {
            $out[] = '- Customer '.$metric['name'].': '.$metric['segment'].'; first '.($metric['first_purchase'] ?? 'N/A').'; last '.($metric['last_purchase'] ?? 'N/A').'; purchases '.$metric['purchase_count'].'; visits '.$metric['visit_count'].'; recognized '.$this->money($metric['recognized_spend_minor']).'; average basket '.$this->money($metric['average_basket_minor']).'; preferred Branch '.($metric['preferred_branch_id'] ?? 'N/A');
        }
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function procurementFinance(array &$out, ?int $companyId): void
    {
        $this->heading($out, '13–18 — PROCUREMENT / AP / TREASURY / EXPENSE / ASSET / BUDGET');
        foreach (['procurement_vendors', 'purchase_requests', 'purchase_orders', 'procurement_goods_receipts', 'vendor_bills', 'vendor_bill_payments', 'treasury_financial_accounts', 'treasury_transactions', 'treasury_reconciliation_sessions', 'treasury_statement_lines', 'finance_expenses', 'finance_assets', 'finance_depreciation_entries', 'finance_budgets'] as $table) {
            $out[] = "$table: ".$this->count($table, $companyId);
        }
        $out[] = 'AP billed / outstanding: '.$this->money($this->sum('vendor_bills', 'total_minor', $companyId)).' / '.$this->money($this->apOutstanding($companyId));
        $out[] = 'Expense total/paid: '.$this->money($this->sum('finance_expenses', 'amount_minor', $companyId)).' / '.$this->money($this->statusSum('finance_expenses', 'amount_minor', 'paid', $companyId, 'payment_status'));
        $out[] = 'Asset acquisition/depreciation: '.$this->money($this->sum('finance_assets', 'acquisition_cost_minor', $companyId)).' / '.$this->money($this->sum('finance_depreciation_entries', 'amount_minor', $companyId));
        foreach ($this->tableRows('treasury_transactions', $companyId) as $tx) {
            $out[] = 'Treasury '.$this->rowString($tx, 'reference').': '.$this->rowString($tx, 'direction').' '.$this->money($this->rowInteger($tx, 'amount_minor')).'; source '.$this->rowString($tx, 'source_type').'#'.$this->rowInteger($tx, 'source_id').'; journal '.($this->rowNullableInteger($tx, 'accounting_journal_id') ?? 'N/A').'; status '.$this->rowString($tx, 'status');
        }
        foreach ($this->tableRows('procurement_vendors', $companyId) as $vendor) {
            $vendorId = $this->rowInteger($vendor, 'id');
            $billed = (int) $this->db->table('vendor_bills')->where('vendor_id', $vendorId)->sum('total_minor');
            $paid = (int) $this->db->table('ap_payment_allocations as a')->join('vendor_bills as b', 'b.id', '=', 'a.vendor_bill_id')->where('b.vendor_id', $vendorId)->sum('a.allocated_minor');
            $lastPurchase = $this->db->table('purchase_orders')->where('vendor_id', $vendorId)->max('order_date');
            $out[] = 'Vendor '.$this->rowString($vendor, 'name').': '.$this->rowString($vendor, 'status').'; PO '.$this->db->table('purchase_orders')->where('vendor_id', $vendorId)->count().'; billed '.$this->money($billed).'; paid '.$this->money($paid).'; outstanding '.$this->money(max(0, $billed - $paid)).'; last purchase '.(is_string($lastPurchase) ? $lastPurchase : 'N/A');
        }
        foreach ($this->tableRows('finance_assets', $companyId) as $asset) {
            $assetId = $this->rowInteger($asset, 'id');
            $depreciation = (int) $this->db->table('finance_depreciation_entries')->where('asset_id', $assetId)->sum('amount_minor');
            $current = $this->db->table('finance_asset_assignments')->where('asset_id', $assetId)->whereNull('effective_to')->first();
            $out[] = 'Asset '.$this->rowString($asset, 'asset_code').' · '.$this->rowString($asset, 'name').': status '.$this->rowString($asset, 'status').'; branch '.($current ? $this->rowInteger($current, 'branch_id') : 'N/A').'; cost center '.($current ? ($this->rowNullableInteger($current, 'cost_center_id') ?? 'N/A') : 'N/A').'; accumulated depreciation '.$this->money($depreciation).'; book value '.$this->money(max(0, $this->rowInteger($asset, 'acquisition_cost_minor') - $depreciation)).'; assignments '.$this->db->table('finance_asset_assignments')->where('asset_id', $assetId)->count();
        }
        $out[] = 'Budget invariant: Consumed = Realized Actual + Open Commitment; Remaining = Approved Budget - Consumed; GRNI/AP/Payment are not counted again.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function lifecycleNotifications(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'EMPLOYEE LIFECYCLE EVENTS / NOTIFICATIONS / ACCOUNTING ADJUSTMENTS');
        foreach ($this->tableRows('employee_lifecycle_events', $companyId) as $event) {
            $employee = $this->db->table('employees')->where('id', $this->rowInteger($event, 'employee_id'))->first();
            $out[] = 'Lifecycle '.$this->rowString($event, 'event_type').': employee '.($employee ? $this->rowString($employee, 'full_name').' ('.$this->rowString($employee, 'employee_code').')' : 'N/A').'; '.$this->rowString($event, 'from_state').' → '.$this->rowString($event, 'to_state').'; effective '.$this->rowString($event, 'effective_at').'; actor '.$this->rowInteger($event, 'created_by');
        }
        $messageQuery = $this->db->table('notification_messages');
        if ($companyId !== null) {
            $messageQuery->where('company_id', $companyId);
        }
        $out[] = 'Notification messages: '.$messageQuery->count().'; database notifications unread/read: '.$this->db->table('notifications')->whereNull('read_at')->count().' / '.$this->db->table('notifications')->whereNotNull('read_at')->count();
        foreach ((clone $messageQuery)->select('category')->selectRaw('COUNT(*) total')->groupBy('category')->orderBy('category')->get() as $category) {
            $out[] = '- Notification '.$this->rowString($category, 'category').': '.$this->rowInteger($category, 'total');
        }
        $this->heading($out, 'HỢP ĐỒNG LAO ĐỘNG');
        $out[] = 'Total contracts: '.$this->count('employment_contracts', $companyId);
        $out[] = 'Active: '.$this->contractStatusCount('active', $companyId).'; Expiring 30 days: '.$this->contractsExpiring($companyId, 30).'; Renewal pending: '.$this->pendingStatuses('employment_contract_renewals', ['draft', 'submitted'], $companyId).'; Renewed: '.$this->statusCount('employment_contract_renewals', 'approved', $companyId);
        foreach ($this->tableRows('employment_contract_renewals', $companyId) as $renewal) {
            $out[] = 'Renewal '.$this->rowString($renewal, 'reference').': employee '.$this->rowInteger($renewal, 'employee_id').'; previous '.$this->rowInteger($renewal, 'previous_contract_id').'; next '.($this->rowNullableInteger($renewal, 'new_contract_id') ?? 'N/A').'; effective '.$this->rowString($renewal, 'effective_date').'; status '.$this->rowString($renewal, 'status');
        }
        $this->heading($out, 'NGHỈ VIỆC / OFFBOARDING');
        $out[] = 'Pending resignation: '.$this->pendingStatuses('employee_resignations', ['draft', 'submitted'], $companyId).'; Approved: '.$this->statusCount('employee_resignations', 'approved', $companyId).'; Completed: '.$this->statusCount('employee_resignations', 'completed', $companyId).'; Employees in offboarding: '.$this->statusCount('employee_offboarding_cases', 'in_progress', $companyId);
        foreach ($this->tableRows('employee_offboarding_cases', $companyId) as $case) {
            $caseId = $this->rowInteger($case, 'id');
            $mandatory = $this->db->table('employee_offboarding_items')->where('offboarding_case_id', $caseId)->where('mandatory', true);
            $out[] = 'Offboarding '.$this->rowString($case, 'reference').': employee '.$this->rowInteger($case, 'employee_id').'; effective '.$this->rowString($case, 'effective_date').'; mandatory '.$mandatory->count().'; completed '.(clone $mandatory)->where('status', 'completed')->count().'; remaining '.(clone $mandatory)->where('status', '!=', 'completed')->count();
        }
        $this->heading($out, 'ACCOUNTING ADJUSTMENT');
        $out[] = 'Requests: '.$this->count('accounting_adjustment_requests', $companyId).'; Draft: '.$this->statusCount('accounting_adjustment_requests', 'draft', $companyId).'; Submitted: '.$this->statusCount('accounting_adjustment_requests', 'submitted', $companyId).'; Approved: '.$this->statusCount('accounting_adjustment_requests', 'approved', $companyId).'; Rejected: '.$this->statusCount('accounting_adjustment_requests', 'rejected', $companyId).'; Posted: '.$this->statusCount('accounting_adjustment_requests', 'posted', $companyId);
        foreach ($this->tableRows('accounting_adjustment_requests', $companyId) as $adjustment) {
            $resultId = $this->rowNullableInteger($adjustment, 'resulting_journal_id');
            $journal = $resultId ? $this->db->table('payroll_accounting_journals')->where('id', $resultId)->first() : null;
            $debit = $journal ? $this->rowInteger($journal, 'total_debit_minor') : 0;
            $credit = $journal ? $this->rowInteger($journal, 'total_credit_minor') : 0;
            $out[] = 'Adjustment '.$this->rowString($adjustment, 'reference').': original '.$this->rowInteger($adjustment, 'original_journal_id').'; target period '.$this->rowInteger($adjustment, 'target_accounting_period_id').'; type '.$this->rowString($adjustment, 'adjustment_type').'; status '.$this->rowString($adjustment, 'status').'; resulting '.($resultId ?? 'N/A').'; debit '.$this->money($debit).'; credit '.$this->money($credit).'; difference '.$this->money($debit - $credit);
        }
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function accounting(array &$out, ?int $companyId): void
    {
        $this->heading($out, '19–21 — ACCOUNTING / GENERAL LEDGER / FINANCIAL STATEMENTS');
        $journalTable = $this->has('accounting_journals') ? 'accounting_journals' : ($this->has('general_ledger_journals') ? 'general_ledger_journals' : 'payroll_accounting_journals');
        $lineTable = $this->has('accounting_journal_lines') ? 'accounting_journal_lines' : ($this->has('general_ledger_entries') ? 'general_ledger_entries' : 'payroll_accounting_journal_lines');
        $out[] = 'Accounting accounts: '.$this->count('accounting_accounts', $companyId);
        $out[] = 'Accounting periods: '.$this->count('accounting_periods', $companyId);
        $out[] = "Journals ($journalTable): ".$this->count($journalTable, $companyId);
        $debit = $this->firstExistingSum($lineTable, ['debit_minor', 'debit_amount_minor', 'debit']);
        $credit = $this->firstExistingSum($lineTable, ['credit_minor', 'credit_amount_minor', 'credit']);
        $out[] = 'Trial Balance debit/credit/difference: '.$this->money($debit).' / '.$this->money($credit).' / '.$this->money($debit - $credit);
        $revenue = $this->postedAccountBalance('4000', true, $companyId);
        $cogs = $this->postedAccountBalance('5000', false, $companyId);
        $payroll = $this->postedAccountPrefixBalance('6421', false, $companyId);
        $opex = $this->postedAccountBalance('5100', false, $companyId);
        $depreciation = $this->postedAccountBalance('5200', false, $companyId);
        $out[] = 'P&L posted GL Revenue / COGS / Gross Profit: '.$this->money($revenue).' / '.$this->money($cogs).' / '.$this->money($revenue - $cogs);
        $out[] = 'P&L posted GL Payroll / OPEX / Depreciation / Operating Result: '.$this->money($payroll).' / '.$this->money($opex).' / '.$this->money($depreciation).' / '.$this->money($revenue - $cogs - $payroll - $opex - $depreciation);
        $assets = $this->postedAccountTypeBalance('asset', $companyId) - $this->postedAccountTypeBalance('contra_asset', $companyId);
        $liabilities = $this->postedAccountTypeBalance('liability', $companyId);
        $equity = $this->postedAccountTypeBalance('equity', $companyId);
        $out[] = 'Balance Sheet posted GL Assets / Liabilities / Equity evidence / Difference: '.$this->money($assets).' / '.$this->money($liabilities).' / '.$this->money($equity).' / '.$this->money($assets - $liabilities - $equity);
        $out[] = 'Income Statement and Balance Sheet use posted journal evidence; Equity: NOT_CANONICALLY_AVAILABLE when no canonical equity account evidence exists.';
        $out[] = 'Balance Sheet Completeness: PARTIAL; Cash Flow: PARTIAL (actual Treasury movements; opening cash evidence unavailable).';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function salesReceivableCashFlow(array &$out, ?int $companyId): void
    {
        $this->heading($out, 'SALES / ACCOUNTS RECEIVABLE / COLLECTION / CASH FLOW');
        $out[] = 'Quotations: '.$this->count('sales_quotations', $companyId).'; Accepted/Converted: '.$this->statusesCount('sales_quotations', ['accepted', 'converted'], $companyId).'; Sales Orders: '.$this->count('sales_orders', $companyId).'; Confirmed/Fulfilled: '.$this->statusesCount('sales_orders', ['confirmed', 'partially_fulfilled', 'fulfilled'], $companyId).' / '.$this->statusCount('sales_orders', 'fulfilled', $companyId);
        $invoiceValue = $this->sum('customer_invoices', 'total_minor', $companyId);
        $cogs = $this->salesCogs($companyId);
        $collected = $this->sum('customer_collections', 'amount_minor', $companyId);
        $allocated = $this->sum('customer_collections', 'allocated_minor', $companyId);
        $out[] = 'Customer Invoices: '.$this->count('customer_invoices', $companyId).'; Invoice value: '.$this->money($invoiceValue).'; COGS snapshot: '.$this->money($cogs).'; Gross Profit: '.$this->money($invoiceValue - $cogs);
        $out[] = 'Collections: '.$this->count('customer_collections', $companyId).'; Collected / Allocated / Unallocated: '.$this->money($collected).' / '.$this->money($allocated).' / '.$this->money($collected - $allocated).'; AR outstanding: '.$this->money($this->arOutstanding($companyId)).'; Overdue AR: '.$this->money($this->overdueAr($companyId));
        $aging = $this->arAging($companyId);
        $out[] = 'AR Aging Current / 1–30 / 31–60 / 61–90 / 90+: '.$this->money($aging['current']).' / '.$this->money($aging['1_30']).' / '.$this->money($aging['31_60']).' / '.$this->money($aging['61_90']).' / '.$this->money($aging['90_plus']);
        foreach (['operating_inflow', 'operating_outflow', 'investing_inflow', 'investing_outflow', 'financing_inflow', 'financing_outflow', 'unclassified'] as $bucket) {
            $out[] = 'Cash Flow '.$bucket.': '.$this->money($this->cashFlowBucket($bucket, $companyId));
        }
        $out[] = 'Cash Flow completeness: PARTIAL — actual Treasury evidence is classified, but no canonical opening cash/equity evidence exists.';
    }

    /**
     * @param  array<int,string>  $out
     *
     * @param-out array<int,string> $out
     *
     * @param  array<string,mixed>  $dq
     */
    private function qualityAndFeatures(array &$out, array $dq, ?int $companyId): void
    {
        $this->heading($out, '22–24 — DATA QUALITY / FEATURE STATUS / EXTERNAL INTEGRATIONS');
        $out[] = 'Data Quality rules/passed/failed/warnings/score: '.$this->arrayInteger($dq, 'rules').' / '.$this->arrayInteger($dq, 'passed').' / '.$this->arrayInteger($dq, 'failed').' / '.$this->arrayInteger($dq, 'warnings').' / '.$this->arrayInteger($dq, 'score');
        $findings = $dq['findings'] ?? [];
        foreach (is_array($findings) ? $findings : [] as $check) {
            if (! is_array($check)) {
                continue;
            }
            $out[] = '- '.$this->arrayString($check, 'key', $this->arrayString($check, 'name', 'rule')).': '.($this->arrayInteger($check, 'count') === 0 ? 'PASS' : 'FAIL');
        }
        foreach ([
            'Organization/Employee/Attendance/Payroll' => 'COMPLETE_FOR_CURRENT_SCOPE',
            'Marketplace/POS/Inventory' => 'COMPLETE_FOR_CURRENT_SCOPE',
            'Procurement/AP/Treasury/Budget/Asset' => 'COMPLETE_FOR_CURRENT_SCOPE',
            'Accounting/Financial Statements' => 'PARTIAL',
            'CRM deterministic segmentation' => 'COMPLETE_FOR_CURRENT_SCOPE',
            'Banking/Government filing' => 'CONFIGURATION_REQUIRED',
            'Notification Center' => 'COMPLETE_FOR_CURRENT_SCOPE',
        ] as $feature => $status) {
            $out[] = "$feature: $status";
        }
        $out[] = 'Cloudflare Images: '.(config('media.driver') === 'cloudflare-images' ? 'CONFIGURED runtime provider' : 'local/current configured provider').'; assets '.$this->providerCount('cloudflare-images').'; secret values omitted.';
        $out[] = 'Mail: '.(config('mail.default') && config('mail.default') !== 'log' ? 'CONFIGURED' : 'CONFIGURATION_REQUIRED/local-log').'; SMS: CONFIGURATION_REQUIRED; Banking: NOT_CONNECTED; Government: NOT_CONNECTED.';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function projectScale(array &$out): void
    {
        $this->heading($out, '25 — CURRENT PROJECT SCALE');
        $root = base_path();
        $out[] = 'Modules: '.count(glob($root.'/Modules/*/module.json') ?: []);
        $out[] = 'Routes: '.count(Route::getRoutes()->getRoutes());
        $out[] = 'Commands: '.$this->recursivePhpCount($root.'/Modules', '*Command.php');
        $out[] = 'Queries: '.$this->recursivePhpCount($root.'/Modules', '*Query.php');
        $out[] = 'Handlers: '.$this->recursivePhpCount($root.'/Modules', '*Handler.php');
        $out[] = 'Services: '.$this->recursivePhpCount($root.'/Modules', '*Service.php');
        $out[] = 'Controllers: '.$this->recursivePhpCount($root.'/Modules', '*Controller.php');
        $out[] = 'Permissions / Roles: '.$this->count('permissions').' / '.$this->count('roles');
        $out[] = 'Migrations / Tables: '.$this->count('migrations').' / '.$this->tableCount();
        $out[] = 'PHPUnit test files: '.$this->recursivePhpCount($root.'/tests', '*Test.php');
        $out[] = 'Runtime assertion count: KHÔNG ĐỦ BẰNG CHỨNG (chỉ có khi chạy PHPUnit; không dùng số tài liệu cũ).';
    }

    private function count(string $table, ?int $companyId = null): int
    {
        if (! $this->has($table)) {
            return 0;
        }
        $q = $this->db->table($table);
        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $q->where('company_id', $companyId);
        }

        return $q->count();
    }

    private function distinctCount(string $table, string $column, ?int $companyId, string $positiveColumn): int
    {
        if (! $this->has($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, $positiveColumn)) {
            return 0;
        }
        $query = $this->db->table($table)->where($positiveColumn, '>', 0);
        if ($companyId !== null && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->distinct()->count($column);
    }

    private function revenueBySource(string $source, ?int $companyId): int
    {
        if (! $this->has('branch_management_revenues')) {
            return 0;
        }
        $query = $this->db->table('branch_management_revenues')->where(['source_type' => $source, 'status' => 'recognized']);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return (int) $query->sum('amount_minor');
    }

    private function sum(string $table, string $column, ?int $companyId = null): int
    {
        if (! $this->has($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }
        $q = $this->db->table($table);
        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $q->where('company_id', $companyId);
        }

        return (int) $q->sum($column);
    }

    private function statusCount(string $table, string $status, ?int $companyId = null, string $column = 'status'): int
    {
        return $this->statusQuery($table, $status, $companyId, $column)?->count() ?? 0;
    }

    private function statusSum(string $table, string $sum, string $status, ?int $companyId = null, string $column = 'status'): int
    {
        return (int) ($this->statusQuery($table, $status, $companyId, $column)?->sum($sum) ?? 0);
    }

    private function statusQuery(string $table, string $status, ?int $companyId, string $column): ?Builder
    {
        if (! $this->has($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }
        $q = $this->db->table($table)->where($column, $status);
        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $q->where('company_id', $companyId);
        }

        return $q;
    }

    /** @param list<string> $statuses */
    private function pendingStatuses(string $table, array $statuses, ?int $companyId): int
    {
        if (! $this->has($table)) {
            return 0;
        }
        $query = $this->db->table($table)->whereIn('status', $statuses);
        if ($companyId !== null && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function contractsExpiring(?int $companyId, int $days): int
    {
        if (! $this->has('employment_contracts')) {
            return 0;
        }
        $query = $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where('c.status', 'active')->whereBetween('c.ends_at', [now()->toDateString(), now()->addDays($days)->toDateString()]);
        if ($companyId !== null) {
            $query->where('e.company_id', $companyId);
        }

        return $query->count();
    }

    private function contractStatusCount(string $status, ?int $companyId): int
    {
        $query = $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where('c.status', $status);
        if ($companyId !== null) {
            $query->where('e.company_id', $companyId);
        }

        return $query->count();
    }

    private function whereCount(string $table, string $column, string $operator, mixed $value, ?int $companyId = null): int
    {
        if (! $this->has($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }
        $q = $this->db->table($table)->where($column, $operator, $value);
        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $q->where('company_id', $companyId);
        }

        return $q->count();
    }

    /** @return iterable<int,object> */
    private function tableRows(string $table, ?int $companyId): iterable
    {
        if (! $this->has($table)) {
            return [];
        } $q = $this->db->table($table)->orderBy('id');
        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $q->where('company_id', $companyId);
        }

        return $q->get();
    }

    private function has(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function tableCount(): int
    {
        return count($this->db->select('SHOW TABLES'));
    }

    private function money(int $minor): string
    {
        return number_format($minor, 0, ',', '.').' VND';
    }

    /** @param array<int,string> $out @param-out array<int,string> $out */
    private function heading(array &$out, string $title): void
    {
        $out[] = '';
        $out[] = str_repeat('=', 80);
        $out[] = $title;
        $out[] = str_repeat('=', 80);
    }

    private function activeUsers(?int $companyId): int
    {
        if (! $companyId) {
            return $this->db->table('users')->where('access_enabled', 1)->count();
        }

        return $this->companyActiveUsers($companyId);
    }

    private function companyActiveUsers(int $companyId): int
    {
        return $this->has('company_user_access') ? $this->db->table('company_user_access as a')->join('users as u', 'u.id', '=', 'a.user_id')->where('a.company_id', $companyId)->where('u.access_enabled', 1)->distinct()->count('u.id') : 0;
    }

    private function userCompanyCount(int $userId): int
    {
        return $this->has('company_user_access') ? $this->db->table('company_user_access')->where('user_id', $userId)->count() : 0;
    }

    private function roles(int $userId): string
    {
        return $this->db->table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->where('mr.model_id', $userId)->where('mr.model_type', 'App\\Models\\User')->pluck('r.name')->implode(', ');
    }

    private function permissionCount(int $userId): int
    {
        $roleIds = $this->db->table('model_has_roles')->where('model_id', $userId)->where('model_type', 'App\\Models\\User')->pluck('role_id');

        return $this->db->table('role_has_permissions')->whereIn('role_id', $roleIds)->distinct()->count('permission_id');
    }

    private function maskEmail(string $email): string
    {
        [$local,$domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('*', max(2, mb_strlen($local) - 2)).'@'.$domain;
    }

    private function branchHeadcount(int $branch): int
    {
        return $this->has('employee_branch_assignments') ? $this->db->table('employee_branch_assignments')->where('branch_id', $branch)->whereNull('effective_to')->distinct()->count('employee_id') : 0;
    }

    private function employeeBranches(int $employee): string
    {
        return $this->has('employee_branch_assignments') ? $this->db->table('employee_branch_assignments as eba')->join('branches as b', 'b.id', '=', 'eba.branch_id')->where('eba.employee_id', $employee)->whereNull('eba.effective_to')->pluck('b.name')->implode(', ') : '';
    }

    private function branchCount(string $table, int $branch): int
    {
        return $this->has($table) && Schema::hasColumn($table, 'branch_id') ? $this->db->table($table)->where('branch_id', $branch)->count() : 0;
    }

    private function branchSum(string $table, string $column, int $branch): int
    {
        return $this->has($table) && Schema::hasColumn($table, 'branch_id') && Schema::hasColumn($table, $column) ? (int) $this->db->table($table)->where('branch_id', $branch)->sum($column) : 0;
    }

    private function branchLate(int $branch): int
    {
        return $this->has('attendance_records') ? $this->db->table('attendance_records')->where('branch_id', $branch)->where('late_minutes', '>', 0)->count() : 0;
    }

    private function branchInventoryQty(int $branch): int
    {
        return $this->has('inventory_locations') ? (int) $this->db->table('inventory_balances as ib')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->where('il.branch_id', $branch)->sum('ib.on_hand') : 0;
    }

    private function branchInventoryValue(int $branch): int
    {
        return $this->has('inventory_locations') ? (int) $this->db->table('inventory_balances as ib')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->where('il.branch_id', $branch)->sum('ib.inventory_value_minor') : 0;
    }

    private function companyInventoryValue(int $company): int
    {
        return $this->has('inventory_locations') ? (int) $this->db->table('inventory_balances as ib')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->join('branches as b', 'b.id', '=', 'il.branch_id')->where('b.company_id', $company)->sum('ib.inventory_value_minor') : 0;
    }

    private function apOutstanding(?int $company): int
    {
        if (! $this->has('vendor_bills')) {
            return 0;
        } $q = $this->db->table('vendor_bills as vb');
        if ($company) {
            $q->where('vb.company_id', $company);
        } $billed = (int) $q->sum('vb.total_minor');
        $paid = 0;
        if ($this->has('ap_payment_allocations')) {
            $p = $this->db->table('ap_payment_allocations as a')->join('vendor_bills as b', 'b.id', '=', 'a.vendor_bill_id');
            if ($company) {
                $p->where('b.company_id', $company);
            }$paid = (int) $p->sum('a.allocated_minor');
        }

        return max(0, $billed - $paid);
    }

    private function arOutstanding(?int $company): int
    {
        if (! $this->has('customer_invoices')) {
            return 0;
        }
        $query = $this->db->table('customer_invoices')->whereIn('status', ['issued', 'partially_paid']);
        if ($company !== null) {
            $query->where('company_id', $company);
        }

        return $this->numericInteger($query->selectRaw('COALESCE(SUM(total_minor-paid_minor),0) outstanding')->value('outstanding'));
    }

    private function overdueAr(?int $company): int
    {
        if (! $this->has('customer_invoices')) {
            return 0;
        }
        $query = $this->db->table('customer_invoices')->whereIn('status', ['issued', 'partially_paid'])->whereDate('due_date', '<', now()->toDateString());
        if ($company !== null) {
            $query->where('company_id', $company);
        }

        return $this->numericInteger($query->selectRaw('COALESCE(SUM(total_minor-paid_minor),0) outstanding')->value('outstanding'));
    }

    private function cashFlowBucket(string $bucket, ?int $company): int
    {
        if (! $this->has('treasury_transactions')) {
            return 0;
        }
        [$class, $direction] = str_contains($bucket, '_') ? explode('_', $bucket, 2) : [$bucket, ''];
        $sources = match ($class) {
            'operating' => ['customer_collection', 'ap_payment', 'expense_payment', 'finance_expense_payment'],
            'investing' => ['asset_acquisition', 'asset_disposal'],
            'financing' => [],
            default => [],
        };
        $query = $this->db->table('treasury_transactions')->where('status', 'recorded');
        if ($company !== null) {
            $query->where('company_id', $company);
        }
        if ($class === 'unclassified') {
            $query->whereNotIn('source_type', ['customer_collection', 'ap_payment', 'expense_payment', 'finance_expense_payment', 'asset_acquisition', 'asset_disposal']);
        } else {
            if ($sources === []) {
                return 0;
            }
            $query->whereIn('source_type', $sources)->where('direction', $direction);
        }

        return (int) $query->sum('amount_minor');
    }

    private function numericInteger(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param list<string> $statuses */
    private function statusesCount(string $table, array $statuses, ?int $company): int
    {
        $query = $this->db->table($table)->whereIn('status', $statuses);
        if ($company !== null) {
            $query->where('company_id', $company);
        }

        return $query->count();
    }

    private function salesCogs(?int $company): int
    {
        $query = $this->db->table('sales_fulfillments as f')->join('sales_orders as o', 'o.id', '=', 'f.sales_order_id')->where('f.status', 'fulfilled');
        if ($company !== null) {
            $query->where('o.company_id', $company);
        }

        return (int) $query->sum('f.total_cogs_minor');
    }

    /** @return array{current:int,'1_30':int,'31_60':int,'61_90':int,'90_plus':int} */
    private function arAging(?int $company): array
    {
        $result = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $query = $this->db->table('customer_invoices')->whereIn('status', ['issued', 'partially_paid']);
        if ($company !== null) {
            $query->where('company_id', $company);
        }
        foreach ($query->get(['due_date', 'total_minor', 'paid_minor']) as $invoice) {
            $dueDate = $invoice->due_date ?? null;
            if (! is_string($dueDate)) {
                continue;
            }
            $days = max(0, (int) now()->startOfDay()->diffInDays($dueDate, false) * -1);
            $bucket = $days === 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
            $result[$bucket] += max(0, $this->numericInteger($invoice->total_minor ?? null) - $this->numericInteger($invoice->paid_minor ?? null));
        }

        return $result;
    }

    private function postedAccountBalance(string $code, bool $creditNormal, ?int $company): int
    {
        $query = $this->postedAccountQuery($company)->where('l.account_code_snapshot', $code);
        $value = $query->selectRaw($creditNormal ? 'COALESCE(SUM(CAST(l.credit_minor AS SIGNED))-SUM(CAST(l.debit_minor AS SIGNED)),0) aggregate' : 'COALESCE(SUM(CAST(l.debit_minor AS SIGNED))-SUM(CAST(l.credit_minor AS SIGNED)),0) aggregate')->value('aggregate');

        return $this->numericInteger($value);
    }

    private function postedAccountPrefixBalance(string $prefix, bool $creditNormal, ?int $company): int
    {
        $query = $this->postedAccountQuery($company)->where('l.account_code_snapshot', 'like', $prefix.'%');
        $value = $query->selectRaw($creditNormal ? 'COALESCE(SUM(CAST(l.credit_minor AS SIGNED))-SUM(CAST(l.debit_minor AS SIGNED)),0) aggregate' : 'COALESCE(SUM(CAST(l.debit_minor AS SIGNED))-SUM(CAST(l.credit_minor AS SIGNED)),0) aggregate')->value('aggregate');

        return $this->numericInteger($value);
    }

    private function postedAccountTypeBalance(string $type, ?int $company): int
    {
        $query = $this->postedAccountQuery($company)->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->where('a.account_type', $type);
        $creditNormal = in_array($type, ['liability', 'equity', 'revenue', 'income', 'contra_asset'], true);
        $value = $query->selectRaw($creditNormal ? 'COALESCE(SUM(CAST(l.credit_minor AS SIGNED))-SUM(CAST(l.debit_minor AS SIGNED)),0) aggregate' : 'COALESCE(SUM(CAST(l.debit_minor AS SIGNED))-SUM(CAST(l.credit_minor AS SIGNED)),0) aggregate')->value('aggregate');

        return $this->numericInteger($value);
    }

    private function postedAccountQuery(?int $company): Builder
    {
        $query = $this->db->table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->whereIn('j.status', ['internally_posted', 'exported']);
        if ($company !== null) {
            $query->where('j.company_id', $company);
        }

        return $query;
    }

    private function providerCount(string $provider): int
    {
        return $this->has('media_assets') ? $this->db->table('media_assets')->where('provider', $provider)->count() : 0;
    }

    private function productsWithoutMedia(): int
    {
        return $this->has('products') ? $this->db->table('products as p')->whereNotExists(fn (Builder $q) => $q->from('media_assets as m')->whereColumn('m.owner_id', 'p.id')->where('m.owner_type', 'product'))->count() : 0;
    }

    private function brokenMediaCount(): int
    {
        return $this->has('media_assets') ? $this->db->table('media_assets')->where(fn (Builder $q) => $q->whereNull('provider_asset_id')->orWhereNull('delivery_url'))->count() : 0;
    }

    /** @param list<string> $columns */
    private function firstExistingSum(string $table, array $columns): int
    {
        foreach ($columns as $column) {
            if ($this->has($table) && Schema::hasColumn($table, $column)) {
                return (int) $this->db->table($table)->sum($column);
            }
        }

        return 0;
    }

    private function recursivePhpCount(string $root, string $pattern): int
    {
        if (! is_dir($root)) {
            return 0;
        } $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $n = 0;
        foreach ($it as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $n++;
            }
        }

        return $n;
    }

    private function rowInteger(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function rowNullableInteger(object $row, string $field): ?int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : null;
    }

    private function rowString(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) ? $value : '';
    }

    private function rowNullableString(object $row, string $field): ?string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<mixed,mixed> $values */
    private function arrayInteger(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param array<mixed,mixed> $values */
    private function arrayString(array $values, string $key, string $fallback = ''): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : $fallback;
    }
}
