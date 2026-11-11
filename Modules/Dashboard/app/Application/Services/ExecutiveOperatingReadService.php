<?php

namespace Modules\Dashboard\Application\Services;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Application\Services\CustomerMetricsService;
use Modules\Finance\Application\Services\FinanceOverviewReadService;

final readonly class ExecutiveOperatingReadService
{
    public function __construct(private ConnectionInterface $db, private DataQualityAuditService $quality, private CustomerMetricsService $customers, private FinanceOverviewReadService $financeOverview, private SupplyChainOverviewReadService $supplyChain) {}

    /** @return array<string, mixed> */
    public function summary(int $companyId, bool $recruitment, bool $attendance, bool $payroll, bool $commerce, bool $inventory, bool $finance, bool $procurement = false, ?int $branchId = null, int $periodDays = 90, bool $purchaseRequests = false, bool $goodsReceipts = false, bool $vendorBills = false): array
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
        $analytics = [
            'filters' => ['branch_id' => $branchId, 'period_days' => $periodDays],
            'inventory_composition' => $inventory ? $this->inventoryComposition($companyId, $branchId) : null,
            'procurement_trend' => $procurement ? $this->procurementTrend($companyId, $branchId, $periodDays) : null,
            'recognized_revenue_trend' => $commerce ? $this->recognizedRevenueTrend($companyId, $branchId, $periodDays) : null,
        ];
        $procurementWorkflow = $this->procurementWorkflow($companyId, $branchId, $purchaseRequests, $procurement, $goodsReceipts, $vendorBills);
        $freshness = ['generated_at' => now()->toIso8601String(), 'strategy' => 'request_read_through', 'cache' => 'none'];

        return compact('organization', 'hrOperations', 'recruitmentData', 'attendanceData', 'payrollData', 'commerceData', 'inventoryData', 'financeData', 'quality', 'analytics', 'procurementWorkflow', 'freshness');
    }

    /**
     * @return array{tasks:list<array{key:string,label:string,count:int,state:string}>,total:int}
     */
    public function procurementWorkflow(int $companyId, ?int $branchId, bool $purchaseRequests, bool $purchaseOrders, bool $goodsReceipts, bool $vendorBills): array
    {
        $tasks = [];
        if ($purchaseRequests) {
            $tasks[] = $this->workflowTask('purchase_requests', 'Yêu cầu mua hàng chờ duyệt', $this->scopedStatusCount('purchase_requests', $companyId, $branchId, ['submitted']), 'SUBMITTED');
        }
        if ($purchaseOrders) {
            $tasks[] = $this->workflowTask('purchase_orders', 'Đơn mua chờ nhận hàng', $this->scopedStatusCount('purchase_orders', $companyId, $branchId, ['approved', 'sent', 'partially_received']), 'APPROVED · SENT · PARTIAL');
        }
        if ($goodsReceipts) {
            $tasks[] = $this->workflowTask('goods_receipts', 'Phiếu nhận hàng chờ xác nhận', $this->scopedStatusCount('procurement_goods_receipts', $companyId, $branchId, ['draft']), 'DRAFT');
        }
        if ($vendorBills) {
            $openBills = $this->db->table('vendor_bills')->where('company_id', $companyId)
                ->whereIn('status', ['approved', 'partially_paid'])
                ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId));
            $tasks[] = $this->workflowTask('vendor_bills', 'Hóa đơn nhà cung cấp chờ thanh toán', (clone $openBills)->count(), 'APPROVED · PARTIALLY PAID');
            $tasks[] = $this->workflowTask('overdue_vendor_bills', 'Hóa đơn nhà cung cấp quá hạn', (clone $openBills)->whereDate('due_date', '<', now()->toDateString())->count(), 'OVERDUE');
        }

        return ['tasks' => $tasks, 'total' => array_sum(array_column($tasks, 'count'))];
    }

    /**
     * Executive action projection over canonical workflow states. Every capability is
     * opt-in so a Dashboard permission can never expand into an unauthorized query.
     *
     * @param  array{sales?:bool,inventory?:bool,leave?:bool,payroll?:bool,documents?:bool,iam?:bool}  $capabilities
     * @return array<string,array{label:string,route:string,state:string,count:int,available:bool}>
     */
    public function enterpriseWorkflow(int $companyId, ?int $branchId, User $actor, array $capabilities): array
    {
        $rows = [];
        $branchScope = function (Builder $query, string $column) use ($actor, $branchId): Builder {
            if ($branchId !== null) {
                return $query->where($column, $branchId);
            }
            if (! $actor->can('organization.tenant-all')) {
                return $query->whereIn($column, $this->db->table('branch_user_access')->where('user_id', $actor->id)->select('branch_id'));
            }

            return $query;
        };

        if ($capabilities['sales'] ?? false) {
            $rows['sales_refunds'] = ! Schema::hasTable('customer_refunds')
                ? $this->blockedActionRow('Hoàn tiền khách hàng', 'admin.sales.index')
                : $this->actionRow('Hoàn tiền khách hàng chờ xử lý', 'admin.sales.index', 'DRAFT · SUBMITTED · APPROVED', $branchScope($this->db->table('customer_refunds')->where('company_id', $companyId), 'branch_id')->whereIn('status', ['draft', 'submitted', 'approved'])->count());
        }
        if ($capabilities['inventory'] ?? false) {
            $rows['stock_counts'] = ! Schema::hasTable('inventory_stock_counts')
                ? $this->blockedActionRow('Kiểm kê tồn kho', 'admin.seller.inventory.governance')
                : $this->actionRow('Kiểm kê đang mở', 'admin.seller.inventory.governance', 'DRAFT · SUBMITTED', $branchScope($this->db->table('inventory_stock_counts')->where('company_id', $companyId), 'branch_id')->whereIn('status', ['draft', 'submitted'])->count());
            $rows['inventory_adjustments'] = ! Schema::hasTable('inventory_adjustments')
                ? $this->blockedActionRow('Điều chỉnh tồn kho', 'admin.seller.inventory.governance')
                : $this->actionRow('Điều chỉnh tồn kho chờ post', 'admin.seller.inventory.governance', 'DRAFT · APPROVED', $branchScope($this->db->table('inventory_adjustments')->where('company_id', $companyId), 'branch_id')->whereIn('status', ['draft', 'approved'])->count());
            $rows['stock_transfers'] = ! Schema::hasTable('inventory_stock_transfers')
                ? $this->blockedActionRow('Điều chuyển kho', 'admin.seller.inventory.movements')
                : $this->actionRow('Điều chuyển kho đang thực hiện', 'admin.seller.inventory.movements', 'DRAFT · IN TRANSIT', $branchScope($this->db->table('inventory_stock_transfers')->where('company_id', $companyId), 'source_branch_id')->whereIn('status', ['draft', 'in_transit'])->count());
        }
        if ($capabilities['leave'] ?? false) {
            $leave = $this->db->table('leave_requests as request')->join('employees as employee', 'employee.id', '=', 'request.employee_id')->where('employee.company_id', $companyId);
            if ($branchId !== null || ! $actor->can('organization.tenant-all')) {
                $leave->whereExists(function (Builder $assignment) use ($actor, $companyId, $branchId): void {
                    $assignment->selectRaw('1')->from('employee_branch_assignments as assignment')
                        ->whereColumn('assignment.employee_id', 'employee.id')->where('assignment.company_id', $companyId)
                        ->whereDate('assignment.effective_from', '<=', now()->toDateString())
                        ->where(fn (Builder $dates): Builder => $dates->whereNull('assignment.effective_to')->orWhereDate('assignment.effective_to', '>=', now()->toDateString()))
                        ->when($branchId !== null, fn (Builder $scope): Builder => $scope->where('assignment.branch_id', $branchId))
                        ->when($branchId === null, fn (Builder $scope): Builder => $scope->whereIn('assignment.branch_id', $this->db->table('branch_user_access')->where('user_id', $actor->id)->select('branch_id')));
                });
            }
            $rows['leave_requests'] = $this->actionRow('Nghỉ phép chờ phê duyệt', 'admin.leave.index', 'PENDING', (clone $leave)->where('request.status', 'pending')->count());
        }
        if ($capabilities['payroll'] ?? false) {
            $rows['payroll_runs'] = $this->actionRow('Payroll cần review/phê duyệt', 'admin.payroll.runs.index', 'REVIEW · APPROVED', $this->db->table('payroll_runs')->where('company_id', $companyId)->whereIn('status', ['review', 'approved'])->count());
        }
        if ($capabilities['documents'] ?? false) {
            $rows['documents'] = $this->actionRow('Tài liệu đang hoạt động', 'admin.documents.index', 'ACTIVE · LIFECYCLE ONLY', $this->db->table('enterprise_documents')->where(['company_id' => $companyId, 'status' => 'ACTIVE'])->count());
        }
        if ($capabilities['iam'] ?? false) {
            $rows['iam_requests'] = $this->actionRow('Yêu cầu truy cập cần xử lý', 'admin.iam.requests.index', 'REQUESTED · REVIEWED · APPROVED', $this->db->table('iam_access_requests')->where('company_id', $companyId)->whereIn('status', ['requested', 'reviewed', 'approved'])->count());
        }

        return $rows;
    }

    /** @return array{label:string,route:string,state:string,count:int,available:bool} */
    private function actionRow(string $label, string $route, string $state, int $count): array
    {
        return [...compact('label', 'route', 'state', 'count'), 'available' => true];
    }

    /** @return array{label:string,route:string,state:string,count:int,available:bool} */
    private function blockedActionRow(string $label, string $route): array
    {
        return ['label' => $label, 'route' => $route, 'state' => 'SOURCE_BLOCKED · SCHEMA UPGRADE REQUIRED', 'count' => 0, 'available' => false];
    }

    /** @param list<string> $statuses */
    private function scopedStatusCount(string $table, int $companyId, ?int $branchId, array $statuses): int
    {
        return $this->db->table($table)->where('company_id', $companyId)->whereIn('status', $statuses)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->count();
    }

    /** @return array{key:string,label:string,count:int,state:string} */
    private function workflowTask(string $key, string $label, int $count, string $state): array
    {
        return compact('key', 'label', 'count', 'state');
    }

    /** @return array{currency:string,status:string,total_minor:int,items:list<array{label:string,value_minor:int,available_units:int}>} */
    private function inventoryComposition(int $companyId, ?int $branchId): array
    {
        $currency = $this->text($this->db->table('companies')->where('id', $companyId)->value('functional_currency_code'));
        $rows = $this->db->table('inventory_balances as balance')
            ->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')
            ->join('stores as store', 'store.id', '=', 'location.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')
            ->join('products as product', 'product.id', '=', 'variant.product_id')
            ->leftJoin('product_categories as category', 'category.id', '=', 'product.product_category_id')
            ->where('seller.company_id', $companyId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('location.branch_id', $branchId))
            ->whereNull('product.deleted_at')
            ->groupBy('category.id', 'category.name')
            ->orderByDesc('value_minor')
            ->limit(12)
            ->get([
                $this->db->raw("COALESCE(category.name, 'Chưa phân loại') as label"),
                $this->db->raw('SUM(balance.inventory_value_minor) as value_minor'),
                $this->db->raw('SUM(GREATEST(balance.on_hand-balance.reserved,0)) as available_units'),
            ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = ['label' => $this->text($row->label ?? null), 'value_minor' => $this->integer($row->value_minor ?? null), 'available_units' => $this->integer($row->available_units ?? null)];
        }
        $total = array_sum(array_column($items, 'value_minor'));

        return ['currency' => $currency, 'status' => $currency === '' ? 'FUNCTIONAL_CURRENCY_REQUIRED' : ($total > 0 ? 'READY' : 'EMPTY'), 'total_minor' => $total, 'items' => $items];
    }

    /** @return array{status:string,currency:?string,series:list<array{period:string,value_minor:int,orders:int}>} */
    private function procurementTrend(int $companyId, ?int $branchId, int $periodDays): array
    {
        $from = CarbonImmutable::today()->subDays($periodDays - 1);
        $base = $this->db->table('purchase_orders')->where('company_id', $companyId)
            ->whereIn('status', ['approved', 'sent', 'partially_received', 'received', 'closed'])
            ->whereDate('order_date', '>=', $from->toDateString())
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId));
        $currencies = (clone $base)->distinct()->pluck('currency')->filter(static fn (mixed $currency): bool => is_string($currency) && $currency !== '')->values();
        if ($currencies->count() !== 1) {
            return ['status' => $currencies->isEmpty() ? 'EMPTY' : 'MULTI_CURRENCY_BLOCKED', 'currency' => null, 'series' => []];
        }
        $series = [];
        foreach ((clone $base)->selectRaw("DATE_FORMAT(order_date, '%Y-%m') period, SUM(subtotal_minor) value_minor, COUNT(*) orders")->groupBy('period')->orderBy('period')->get() as $row) {
            $series[] = ['period' => $this->text($row->period ?? null), 'value_minor' => $this->integer($row->value_minor ?? null), 'orders' => $this->integer($row->orders ?? null)];
        }

        return ['status' => $series === [] ? 'EMPTY' : 'READY', 'currency' => $this->text($currencies->first()), 'series' => $series];
    }

    /** @return array{status:string,currency:?string,series:list<array{period:string,value_minor:int,records:int}>} */
    private function recognizedRevenueTrend(int $companyId, ?int $branchId, int $periodDays): array
    {
        $from = CarbonImmutable::today()->subDays($periodDays - 1);
        $base = $this->db->table('branch_management_revenues')->where('company_id', $companyId)
            ->where('status', 'recognized')->whereIn('source_type', ['commerce_order', 'branch_pos'])
            ->whereDate('recognized_on', '>=', $from->toDateString())
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId));
        $currencies = (clone $base)->distinct()->pluck('currency_code')->filter(static fn (mixed $currency): bool => is_string($currency) && $currency !== '')->values();
        if ($currencies->count() !== 1) {
            return ['status' => $currencies->isEmpty() ? 'EMPTY' : 'MULTI_CURRENCY_BLOCKED', 'currency' => null, 'series' => []];
        }
        $series = [];
        foreach ((clone $base)->selectRaw("DATE_FORMAT(recognized_on, '%Y-%m') period, SUM(amount_minor) value_minor, COUNT(*) records")->groupBy('period')->orderBy('period')->get() as $row) {
            $series[] = ['period' => $this->text($row->period ?? null), 'value_minor' => $this->integer($row->value_minor ?? null), 'records' => $this->integer($row->records ?? null)];
        }

        return ['status' => $series === [] ? 'EMPTY' : 'READY', 'currency' => $this->text($currencies->first()), 'series' => $series];
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
