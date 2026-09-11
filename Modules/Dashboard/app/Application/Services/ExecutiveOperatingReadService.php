<?php

namespace Modules\Dashboard\Application\Services;

use App\Support\DataQuality\DataQualityAuditService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Commerce\Application\Services\CustomerMetricsService;
use Modules\Finance\Application\Services\FinanceOverviewReadService;

final readonly class ExecutiveOperatingReadService
{
    public function __construct(private ConnectionInterface $db, private DataQualityAuditService $quality, private CustomerMetricsService $customers, private FinanceOverviewReadService $financeOverview, private SupplyChainOverviewReadService $supplyChain) {}

    /** @return array<string, mixed> */
    public function summary(int $companyId, bool $recruitment, bool $attendance, bool $payroll, bool $commerce, bool $inventory, bool $finance): array
    {
        $organization = [
            'units' => $this->db->table('organization_units')->where('company_id', $companyId)->count(),
            'positions' => $this->db->table('job_positions')->where('company_id', $companyId)->count(),
            'approved_headcount' => (int) $this->db->table('job_positions')->where('company_id', $companyId)->sum('approved_headcount'),
            'sellers' => $this->db->table('seller_profiles')->where('company_id', $companyId)->count(),
            'stores' => $this->db->table('stores as store')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->where('seller.company_id', $companyId)->whereNull('store.deleted_at')->count(),
        ];
        $customerMetrics = $this->customers->forCompany($companyId);
        $hrOperations = [
            'contracts_expiring_30' => $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where('e.company_id', $companyId)->where('c.status', 'active')->whereBetween('c.ends_at', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'pending_resignations' => $this->db->table('employee_resignations')->where('company_id', $companyId)->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
            'offboarding' => $this->db->table('employee_offboarding_cases')->where('company_id', $companyId)->where('status', 'in_progress')->count(),
            'pending_accounting_adjustments' => $this->db->table('accounting_adjustment_requests')->where('company_id', $companyId)->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
            'crm_customers' => $customerMetrics->count(), 'crm_repeat' => $customerMetrics->where('segment', 'REPEAT')->count(), 'crm_at_risk' => $customerMetrics->where('segment', 'AT_RISK')->count(), 'crm_dormant' => $customerMetrics->where('segment', 'DORMANT')->count(),
        ];

        $recruitmentData = null;
        if ($recruitment) {
            $applications = $this->db->table('applications as application')->join('recruitment_requisitions as requisition', 'requisition.id', '=', 'application.recruitment_requisition_id')->join('job_positions as position', 'position.id', '=', 'requisition.job_position_id')->where('position.company_id', $companyId);
            $recruitmentData = [
                'open_requisitions' => $this->requisitionQuery($companyId)->where('requisition.status', 'open')->count(),
                'vacancies' => $this->integer($this->requisitionQuery($companyId)->where('requisition.status', 'open')->selectRaw('COALESCE(SUM(GREATEST(requisition.vacancies - requisition.filled_hires, 0)), 0) AS aggregate')->value('aggregate')),
                'candidates' => $this->db->table('candidates')->where('company_id', $companyId)->whereNull('deleted_at')->count(),
                'interview_stage' => (clone $applications)->where('application.stage', 'interview')->count(),
                'scheduled_interviews' => $this->db->table('application_interviews')->where('company_id', $companyId)->whereIn('status', ['scheduled', 'completed'])->count(),
                'offers' => $this->db->table('candidate_offers')->where('company_id', $companyId)->count(),
                'hired' => (clone $applications)->where('application.stage', 'hired')->count(),
                'linked_hires' => (clone $applications)->where('application.stage', 'hired')->whereNotNull('application.hired_employee_id')->count(),
            ];
        }

        $attendanceData = null;
        if ($attendance) {
            $operatingDate = $this->db->table('attendance_records')->where('company_id', $companyId)->max('work_date');
            $date = is_string($operatingDate) ? $operatingDate : now()->toDateString();
            $records = $this->db->table('attendance_records')->where('company_id', $companyId)->whereDate('work_date', $date);
            $attendanceData = [
                'date' => $date,
                'records' => (clone $records)->count(),
                'present' => (clone $records)->whereNotNull('check_in_at')->count(),
                'late' => (clone $records)->where('late_minutes', '>', 0)->count(),
                'late_minutes' => (int) (clone $records)->sum('late_minutes'),
                'leave' => $this->db->table('leave_requests')->where('company_id', $companyId)->where('status', 'approved')->whereDate('starts_at', '<=', $date)->whereDate('ends_at', '>=', $date)->count(),
                'approved_overtime_minutes' => (int) $this->db->table('overtime_requests')->where('company_id', $companyId)->where('status', 'approved')->whereDate('work_date', $date)->sum('approved_minutes'),
            ];
        }

        $payrollData = null;
        if ($payroll) {
            $run = $this->db->table('payroll_runs as run')->join('payroll_periods as period', 'period.id', '=', 'run.payroll_period_id')->where('run.company_id', $companyId)->orderByDesc('run.period_end')->orderByDesc('run.id')->first(['run.id', 'run.code', 'run.status', 'run.employee_count', 'run.gross_total_minor', 'run.deduction_total_minor', 'run.net_total_minor', 'run.personal_income_tax_total_minor', 'run.insurance_employee_total_minor', 'run.insurance_employer_total_minor', 'run.employer_contribution_total_minor', 'period.code as period_code']);
            if ($run !== null) {
                $runId = $this->integer($run->id ?? null);
                $bonus = $this->integer($this->db->table('payroll_item_components')->where('payroll_run_id', $runId)->where('component_code_snapshot', 'BRANCH_BONUS')->sum('amount_minor'));
                $overtime = $this->integer($this->db->table('payroll_item_components')->where('payroll_run_id', $runId)->whereIn('component_code_snapshot', ['WEEKDAY-OT-DEMO', 'WEEKEND-OT-DEMO', 'HOLIDAY-OT-DEMO'])->sum('amount_minor'));
                $payrollData = ['run_id' => $runId, 'period' => $this->text($run->period_code ?? null), 'status' => $this->text($run->status ?? null), 'employees' => $this->integer($run->employee_count ?? null), 'gross' => $this->integer($run->gross_total_minor ?? null), 'deductions' => $this->integer($run->deduction_total_minor ?? null), 'net' => $this->integer($run->net_total_minor ?? null), 'pit' => $this->integer($run->personal_income_tax_total_minor ?? null), 'insurance_employee' => $this->integer($run->insurance_employee_total_minor ?? null), 'insurance_employer' => $this->integer($run->insurance_employer_total_minor ?? null), 'employer_contribution' => $this->integer($run->employer_contribution_total_minor ?? null), 'bonus' => $bonus, 'overtime' => $overtime];
            }
        }

        $commerceData = null;
        if ($commerce) {
            $marketplaceRevenue = (int) $this->db->table('branch_management_revenues')->where('company_id', $companyId)->where(['source_type' => 'commerce_order', 'status' => 'recognized'])->sum('amount_minor');
            $posRevenue = (int) $this->db->table('branch_management_revenues')->where('company_id', $companyId)->where(['source_type' => 'branch_pos', 'status' => 'recognized'])->sum('amount_minor');
            $reversal = (int) $this->db->table('commerce_sale_reversals')->where('company_id', $companyId)->where('status', 'completed')->sum('amount_minor');
            $receiptStats = $this->db->table('branch_pos_receipts')->where('company_id', $companyId)->where('status', 'paid')->selectRaw('COUNT(*) receipts, COALESCE(SUM(total_minor),0) revenue')->first();
            $receiptCount = $this->integer($receiptStats?->receipts);
            $commerceData = [
                'orders' => $this->orderQuery($companyId)->count(),
                'paid_orders' => $this->orderQuery($companyId)->where('order.payment_status', 'paid')->count(),
                'fulfilled_orders' => $this->orderQuery($companyId)->where('order.status', 'fulfilled')->count(),
                'marketplace_revenue' => $marketplaceRevenue,
                'visits' => $this->db->table('customer_visits')->where('company_id', $companyId)->count(),
                'receipts' => $receiptCount,
                'pos_revenue' => $posRevenue,
                'average_ticket' => $receiptCount > 0 ? intdiv($this->integer($receiptStats?->revenue), $receiptCount) : 0,
                'customers' => $this->db->table('commerce_customers')->where('company_id', $companyId)->count(),
                'repeat_customers' => $this->repeatCustomers($companyId),
                'reversals' => $reversal,
                'net_recognized_revenue' => $marketplaceRevenue + $posRevenue - $reversal,
                'erp_sales_orders' => $this->db->table('sales_orders')->where('company_id', $companyId)->count(),
                'customer_invoices' => $this->db->table('customer_invoices')->where('company_id', $companyId)->count(),
                'ar_outstanding' => $this->integer($this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid'])->selectRaw('COALESCE(SUM(total_minor-paid_minor),0) outstanding')->value('outstanding')),
                'customer_collections' => (int) $this->db->table('customer_collections')->where('company_id', $companyId)->sum('allocated_minor'),
            ];
        }

        $inventoryData = null;
        if ($inventory) {
            $supplyChain = $this->supplyChain->read($companyId);
            $balances = $this->db->table('inventory_balances as balance')->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')->join('stores as store', 'store.id', '=', 'location.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->where('seller.company_id', $companyId);
            $movements = $this->db->table('inventory_movements as movement')->join('inventory_locations as location', 'location.id', '=', 'movement.inventory_location_id')->join('stores as store', 'store.id', '=', 'location.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->where('seller.company_id', $companyId);
            $inventoryData = [
                'skus' => (clone $balances)->distinct()->count('balance.product_variant_id'),
                'on_hand' => (int) (clone $balances)->sum('balance.on_hand'),
                'low_stock' => (clone $balances)->whereRaw('balance.on_hand - balance.reserved BETWEEN 1 AND 5')->count(),
                'out_of_stock' => (clone $balances)->whereRaw('balance.on_hand - balance.reserved <= 0')->count(),
                'stock_in' => (int) (clone $movements)->where('movement.quantity_delta', '>', 0)->sum('movement.quantity_delta'),
                'stock_out' => abs((int) (clone $movements)->where('movement.quantity_delta', '<', 0)->sum('movement.quantity_delta')),
                'recent_movements' => (clone $movements)->where('movement.created_at', '>=', now()->subDays(30))->count(),
                'open_transfers' => $this->db->table('inventory_stock_transfers')->where('company_id', $companyId)->whereIn('status', ['draft', 'in_transit'])->count(),
                'inventory_value' => $this->integer(data_get($supplyChain, 'inventory.inventory_value_minor')),
                'open_purchase_orders' => $this->integer(data_get($supplyChain, 'supply.open_purchase_orders')),
                'pending_receipts' => $this->integer(data_get($supplyChain, 'supply.pending_receipts')),
                'open_sales_demand' => $this->integer(data_get($supplyChain, 'demand.erp_sales.open_orders')),
                'pending_fulfillment' => $this->integer(data_get($supplyChain, 'demand.marketplace_ready')),
                'in_transit_shipments' => $this->integer(data_get($supplyChain, 'fulfillment.in_transit')),
            ];
        }

        $financeData = null;
        if ($finance) {
            $overview = $this->financeOverview->read($companyId, null, null, null);
            $financeData = [
                'revenue' => $this->integer(data_get($overview, 'income.revenue')),
                'cogs' => $this->integer(data_get($overview, 'income.cogs')),
                'gross_profit' => $this->integer(data_get($overview, 'income.gross_profit')),
                'operating_profit' => $this->integer(data_get($overview, 'income.profit')),
                'ar_outstanding' => $this->integer($overview['ar_outstanding'] ?? null),
                'ap_outstanding' => $this->integer($overview['ap_outstanding'] ?? null),
                'net_cash_movement' => $this->integer(data_get($overview, 'cash_flow.net_movement')),
                'budget_available' => $this->integer(data_get($overview, 'budget.available')),
            ];
        }

        $quality = $this->quality->snapshot();

        return compact('organization', 'hrOperations', 'recruitmentData', 'attendanceData', 'payrollData', 'commerceData', 'inventoryData', 'financeData', 'quality');
    }

    private function requisitionQuery(int $companyId): Builder
    {
        return $this->db->table('recruitment_requisitions as requisition')->join('job_positions as position', 'position.id', '=', 'requisition.job_position_id')->where('position.company_id', $companyId);
    }

    private function orderQuery(int $companyId): Builder
    {
        return $this->db->table('orders as order')->join('seller_profiles as seller', 'seller.id', '=', 'order.seller_profile_id')->where('seller.company_id', $companyId);
    }

    private function repeatCustomers(int $companyId): int
    {
        $customers = $this->db->table('branch_pos_receipts as receipt')->join('customer_visits as visit', 'visit.id', '=', 'receipt.customer_visit_id')->where('receipt.company_id', $companyId)->groupBy('visit.customer_id')->havingRaw('COUNT(receipt.id) > 1')->select('visit.customer_id');

        return $this->db->table($customers, 'repeat_customer')->count();
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
