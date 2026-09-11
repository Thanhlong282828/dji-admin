<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class BranchDashboardReadService
{
    public function __construct(private ConnectionInterface $db, private BranchFinancialPerformanceService $financials) {}

    /** @return array{branches:int,assigned_employees:int,unassigned_employees:int,sales:array{marketplace_orders:int,pos_receipts:int,customers:int,visits_open:int,products_sold:int,services_sold:int,recognized_revenue:int,low_stock:int}|null,financials:array<string,int>|null,financial_trend:list<array{month:string,revenue:int,payroll:int,operating:int,profit:int}>} */
    public function summary(int $companyId, bool $canViewFinancials, bool $canViewCommerce = false): array
    {
        $branches = $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->count();
        $assignedEmployees = $this->db->table('employee_branch_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->where('a.company_id', $companyId)->where('e.company_id', $companyId)->where('a.assignment_type', 'primary')
            ->whereDate('a.effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', today()))
            ->distinct()->count('a.employee_id');
        $activeEmployees = $this->db->table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation', 'preboarding'])->whereNull('deleted_at')->count();

        $trend = [];
        if ($canViewFinancials) {
            foreach (range(5, 0) as $monthsAgo) {
                $month = now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m');
                $totals = $this->financials->snapshot($companyId, $month)['totals'];
                $trend[] = ['month' => $month, 'revenue' => $totals['revenue'], 'payroll' => $totals['payroll'], 'operating' => $totals['operating'] + $totals['rent'] + $totals['depreciation'], 'profit' => $totals['profit']];
            }
        }

        $sales = null;
        if ($canViewCommerce) {
            $sales = [
                'marketplace_orders' => $this->db->table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->where('sp.company_id', $companyId)->count(),
                'pos_receipts' => $this->db->table('branch_pos_receipts')->where('company_id', $companyId)->where('status', 'paid')->count(),
                'customers' => $this->db->table('commerce_customers')->where('company_id', $companyId)->where('status', 'active')->count(),
                'visits_open' => $this->db->table('customer_visits')->where('company_id', $companyId)->where('status', 'checked_in')->count(),
                'products_sold' => (int) $this->db->table('customer_visit_products as line')->join('customer_visits as visit', 'visit.id', '=', 'line.customer_visit_id')->where('visit.company_id', $companyId)->where('visit.status', 'checked_out')->sum('line.quantity'),
                'services_sold' => (int) $this->db->table('customer_visit_services as line')->join('customer_visits as visit', 'visit.id', '=', 'line.customer_visit_id')->where('visit.company_id', $companyId)->where('visit.status', 'checked_out')->sum('line.quantity'),
                'recognized_revenue' => (int) $this->db->table('branch_management_revenues')->where('company_id', $companyId)->where('status', 'recognized')->whereIn('source_type', ['branch_pos', 'commerce_order'])->sum('amount_minor') - (int) $this->db->table('commerce_sale_reversals')->where('company_id', $companyId)->where('status', 'completed')->sum('amount_minor'),
                'low_stock' => $this->db->table('inventory_balances as balance')->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')->join('branches as branch', 'branch.id', '=', 'location.branch_id')->where('branch.company_id', $companyId)->whereRaw('balance.on_hand - balance.reserved <= 5')->count(),
            ];
        }

        return [
            'branches' => $branches,
            'assigned_employees' => $assignedEmployees,
            'unassigned_employees' => max(0, $activeEmployees - $assignedEmployees),
            'sales' => $sales,
            'financials' => $canViewFinancials ? $this->financials->snapshot($companyId, now()->format('Y-m'))['totals'] : null,
            'financial_trend' => $trend,
        ];
    }
}
