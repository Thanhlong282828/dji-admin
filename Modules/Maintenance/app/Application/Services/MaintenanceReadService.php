<?php

namespace Modules\Maintenance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;

final readonly class MaintenanceReadService
{
    public function __construct(private ConnectionInterface $db, private MaintenanceWorkOrderCloseoutService $closeout) {}

    /** @return array<string, int> */
    public function executiveSummary(int $companyId): array
    {
        $row = $this->db->table('maintenance_work_orders')->where('company_id', $companyId)->selectRaw("COALESCE(SUM(status NOT IN ('COMPLETED','CLOSED','CANCELLED')),0) open_orders, COALESCE(SUM(status NOT IN ('COMPLETED','CLOSED','CANCELLED') AND scheduled_end<CURRENT_TIMESTAMP),0) overdue, COALESCE(SUM(CASE WHEN status IN ('COMPLETED','CLOSED') THEN downtime_minutes ELSE 0 END),0) downtime_minutes")->first();
        $parts = $this->integer($this->db->table('maintenance_part_requirements as p')->join('maintenance_work_orders as w', 'w.id', '=', 'p.work_order_id')->where('w.company_id', $companyId)->where('p.status', 'ISSUED')->sum('p.actual_cost_minor'));
        $expense = $this->integer($this->db->table('finance_expenses')->where(['company_id' => $companyId, 'source_type' => 'MAINTENANCE_WORK_ORDER'])->whereIn('status', ['approved', 'paid'])->sum('amount_minor'));

        return ['open' => $this->integer($row?->open_orders), 'overdue' => $this->integer($row?->overdue), 'downtime_minutes' => $this->integer($row?->downtime_minutes), 'parts_cost_minor' => $parts, 'expense_cost_minor' => $expense];
    }

    /** @return array<string,mixed> */
    public function read(int $c, ?int $order = null): array
    {
        $requests = $this->db->table('maintenance_requests as r')->join('finance_assets as a', 'a.id', '=', 'r.asset_id')->where('r.company_id', $c);
        $orders = $this->db->table('maintenance_work_orders as w')->join('finance_assets as a', 'a.id', '=', 'w.asset_id')->leftJoin('employees as e', 'e.id', '=', 'w.assigned_employee_id')->where('w.company_id', $c);
        $plans = $this->db->table('preventive_maintenance_plans as p')->join('finance_assets as a', 'a.id', '=', 'p.asset_id')->where('p.company_id', $c);
        $summary = ['requests' => (clone $requests)->count(), 'submitted' => (clone $requests)->where('r.status', 'SUBMITTED')->count(), 'approved' => (clone $requests)->where('r.status', 'APPROVED')->count(), 'converted' => (clone $requests)->where('r.status', 'CONVERTED')->count(), 'work_orders' => (clone $orders)->count(), 'draft' => (clone $orders)->where('w.status', 'DRAFT')->count(), 'planned' => (clone $orders)->where('w.status', 'PLANNED')->count(), 'in_progress' => (clone $orders)->where('w.status', 'IN_PROGRESS')->count(), 'on_hold' => (clone $orders)->where('w.status', 'ON_HOLD')->count(), 'completed' => (clone $orders)->whereIn('w.status', ['COMPLETED', 'CLOSED'])->count(), 'closed' => (clone $orders)->where('w.status', 'CLOSED')->count(), 'cancelled' => (clone $orders)->where('w.status', 'CANCELLED')->count(), 'overdue' => (clone $orders)->whereNotIn('w.status', ['COMPLETED', 'CLOSED', 'CANCELLED'])->where('w.scheduled_end', '<', now())->count(), 'plans' => (clone $plans)->count(), 'active_plans' => (clone $plans)->where('p.is_active', true)->count(), 'due_plans' => (clone $plans)->where('p.is_active', true)->where('p.next_due_date', '<=', today())->count(), 'downtime_minutes' => $this->integer((clone $orders)->whereIn('w.status', ['COMPLETED', 'CLOSED'])->sum('w.downtime_minutes')), 'parts_cost_minor' => $this->integer($this->db->table('maintenance_part_requirements as x')->join('maintenance_work_orders as w', 'w.id', '=', 'x.work_order_id')->where('w.company_id', $c)->sum('x.actual_cost_minor'))];
        $summary['external_expense_count'] = $this->db->table('finance_expenses')->where(['company_id' => $c, 'source_type' => 'MAINTENANCE_WORK_ORDER'])->count();
        $summary['external_cost_minor'] = $this->integer($this->db->table('finance_expenses')->where(['company_id' => $c, 'source_type' => 'MAINTENANCE_WORK_ORDER'])->whereIn('status', ['approved', 'paid'])->sum('amount_minor'));
        $summary['procurement_count'] = $this->db->table('purchase_requests')->where(['company_id' => $c, 'source_type' => 'MAINTENANCE_WORK_ORDER'])->count();
        $assets = $this->db->table('finance_assets')->where('company_id', $c)->whereNotIn('status', ['disposed', 'inactive'])->orderBy('asset_code')->get(['id', 'asset_code', 'name', 'branch_id']);
        $employees = $this->db->table('employees')->where('company_id', $c)->whereIn('employment_status', ['active', 'probation'])->orderBy('employee_code')->get(['id', 'employee_code', 'full_name']);
        if ($order === null) {
            return compact('summary', 'assets', 'employees') + ['requests' => $requests->select('r.*', 'a.asset_code', 'a.name as asset_name')->orderByDesc('r.updated_at')->paginate(20), 'orders' => $orders->select('w.*', 'a.asset_code', 'a.name as asset_name', 'e.full_name as technician_name')->orderByDesc('w.updated_at')->paginate(20), 'plans' => $plans->select('p.*', 'a.asset_code', 'a.name as asset_name')->orderBy('p.next_due_date')->get()];
        }
        $workOrder = (clone $orders)->where('w.id', $order)->first(['w.*', 'a.asset_code', 'a.name as asset_name', 'a.branch_id as asset_branch_id', 'e.full_name as technician_name']);
        abort_unless($workOrder !== null, 404);
        $partsQuery = $this->db->table('maintenance_part_requirements as x')
            ->join('product_variants as v', 'v.id', '=', 'x.product_variant_id')
            ->leftJoin('payroll_accounting_journals as j', static fn (JoinClause $join): JoinClause => $join->on('j.source_id', '=', 'x.id')->where('j.source_type', 'maintenance_part_requirement')->where('j.posting_type', 'maintenance_part_consumption')->whereNull('j.reversal_of_journal_id'))
            ->where('x.work_order_id', $order);
        $returns = collect();
        if (Schema::hasTable('maintenance_part_returns')) {
            $returnTotals = $this->db->table('maintenance_part_returns')->where('status', 'POSTED')->selectRaw('maintenance_part_requirement_id, SUM(quantity) returned_quantity, SUM(returned_value_minor) returned_value_minor')->groupBy('maintenance_part_requirement_id');
            $partsQuery->leftJoinSub($returnTotals, 'rt', 'rt.maintenance_part_requirement_id', '=', 'x.id');
            $parts = $partsQuery->get(['x.*', 'v.sku', 'j.id as accounting_journal_id', 'j.journal_number', $this->db->raw('COALESCE(rt.returned_quantity,0) as returned_quantity'), $this->db->raw('COALESCE(rt.returned_value_minor,0) as returned_value_minor')]);
            $returns = $this->db->table('maintenance_part_returns as r')
                ->join('maintenance_part_requirements as p', 'p.id', '=', 'r.maintenance_part_requirement_id')
                ->join('product_variants as v', 'v.id', '=', 'p.product_variant_id')
                ->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'r.accounting_journal_id')
                ->where(['r.company_id' => $c, 'r.work_order_id' => $order])->orderBy('r.id')
                ->get(['r.*', 'v.sku', 'j.journal_number']);
        } else {
            $parts = $partsQuery->get(['x.*', 'v.sku', 'j.id as accounting_journal_id', 'j.journal_number'])->map(function (object $part): object {
                $part->returned_quantity = 0;
                $part->returned_value_minor = 0;

                return $part;
            });
        }
        $documents = $this->db->table('enterprise_documents')->where(['company_id' => $c, 'owner_module' => 'Maintenance', 'subject_type' => 'MAINTENANCE_WORK_ORDER', 'subject_id' => $order])->count();
        $expenses = $this->db->table('finance_expenses as e')->join('finance_expense_categories as cat', 'cat.id', '=', 'e.expense_category_id')->where(['e.company_id' => $c, 'e.source_type' => 'MAINTENANCE_WORK_ORDER', 'e.source_id' => $order])->orderByDesc('e.expense_date')->get(['e.*', 'cat.name as category_name']);
        $purchaseRequests = $this->db->table('purchase_requests')->where(['company_id' => $c, 'source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->orderByDesc('requested_date')->get();
        $expenseCategories = $this->db->table('finance_expense_categories')->where(['company_id' => $c, 'status' => 'active'])->orderBy('name')->get(['id', 'code', 'name']);
        $costCenters = $this->db->table('cost_centers')->where('company_id', $c)->orderBy('name')->get(['id', 'code', 'name']);
        $externalCost = $this->integer($expenses->whereIn('status', ['approved', 'paid'])->sum('amount_minor'));
        $partsCost = $this->integer($parts->where('status', 'ISSUED')->sum('actual_cost_minor'));
        $reconciliationWarning = null;
        try {
            $reconciliation = $this->closeout->preview($c, $order);
        } catch (\Throwable $exception) {
            $reconciliationWarning = $exception->getMessage() !== '' ? $exception->getMessage() : 'Bằng chứng chi phí chưa sẵn sàng để đóng Work Order.';
            $returnedCost = $this->integer($returns->sum('returned_value_minor'));
            $reconciliation = ['currency' => 'VND', 'gross_spare_cost_minor' => $partsCost, 'returned_spare_cost_minor' => $returnedCost, 'net_spare_cost_minor' => max(0, $partsCost - $returnedCost), 'other_verified_cost_minor' => $externalCost, 'total_actual_cost_minor' => max(0, $partsCost - $returnedCost) + $externalCost, 'issue_movement_ids' => [], 'return_movement_ids' => [], 'issue_journal_ids' => [], 'return_journal_ids' => [], 'expense_ids' => []];
        }
        $acceptance = Schema::hasTable('maintenance_work_order_acceptances') ? $this->db->table('maintenance_work_order_acceptances as x')->leftJoin('users as u', 'u.id', '=', 'x.accepted_by')->where(['x.company_id' => $c, 'x.work_order_id' => $order])->first(['x.*', 'u.name as accepted_by_name']) : null;
        $closeout = Schema::hasTable('maintenance_work_order_closeouts') ? $this->db->table('maintenance_work_order_closeouts as x')->leftJoin('users as u', 'u.id', '=', 'x.closed_by')->where(['x.company_id' => $c, 'x.work_order_id' => $order])->first(['x.*', 'u.name as closed_by_name']) : null;
        $evidenceCost = ['parts_minor' => $reconciliation['gross_spare_cost_minor'], 'returned_minor' => $reconciliation['returned_spare_cost_minor'], 'net_parts_minor' => $reconciliation['net_spare_cost_minor'], 'external_minor' => $reconciliation['other_verified_cost_minor'], 'total_minor' => $reconciliation['total_actual_cost_minor'], 'currency' => $reconciliation['currency']];
        $availableParts = $this->db->table('inventory_balances as b')
            ->join('inventory_locations as l', 'l.id', '=', 'b.inventory_location_id')
            ->join('stores as s', 's.id', '=', 'l.store_id')
            ->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
            ->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('sp.company_id', $c)->whereNull('p.deleted_at')
            ->whereRaw('(b.on_hand - b.reserved) > 0')
            ->orderBy('p.name')->get(['v.id as variant_id', 'l.id as location_id', 'p.name as product_name', 'v.sku', 'b.on_hand', 'b.reserved']);

        return compact('summary', 'assets', 'employees', 'workOrder', 'parts', 'returns', 'documents', 'availableParts', 'expenses', 'purchaseRequests', 'expenseCategories', 'costCenters', 'evidenceCost', 'reconciliation', 'reconciliationWarning', 'acceptance', 'closeout');
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid maintenance numeric aggregate.'));
    }
}
