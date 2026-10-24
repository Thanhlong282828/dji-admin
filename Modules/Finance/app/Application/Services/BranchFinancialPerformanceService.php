<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

final readonly class BranchFinancialPerformanceService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{month:string,rows:list<array<string,int|string>>,totals:array<string,int>} */
    public function snapshot(int $companyId, string $month): array
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01');
        if (! $parsed instanceof CarbonImmutable) {
            throw new \InvalidArgumentException('Tháng báo cáo không hợp lệ.');
        }
        $start = $parsed->startOfMonth();
        $end = $start->endOfMonth();
        $branches = $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name']);
        $revenues = $this->db->table('branch_management_revenues')->where('company_id', $companyId)->where('status', 'recognized')->whereBetween('recognized_on', [$start, $end])->groupBy('branch_id')
            ->selectRaw("branch_id, SUM(amount_minor) total, COUNT(*) source_count, SUM(CASE WHEN reference LIKE 'LOCAL-REV-%' OR EXISTS (SELECT 1 FROM commerce_payment_records p WHERE p.order_id = branch_management_revenues.order_id AND p.method = 'internal_demo') THEN 1 ELSE 0 END) synthetic_count, GROUP_CONCAT(DISTINCT source_type ORDER BY source_type SEPARATOR ', ') source_types")
            ->get()->keyBy('branch_id');
        $reversals = $this->db->table('commerce_sale_reversals')->where('company_id', $companyId)->where('status', 'completed')->whereBetween('completed_at', [$start, $end])->groupBy('branch_id')->selectRaw('branch_id, SUM(amount_minor) total, COUNT(*) source_count')->get()->keyBy('branch_id');
        $expenses = $this->db->table('branch_operating_expenses')->where('company_id', $companyId)->where('status', 'approved')->whereBetween('incurred_on', [$start, $end])->groupBy('branch_id')->selectRaw('branch_id, SUM(amount_minor) total')->pluck('total', 'branch_id');
        $rents = $this->db->table('branch_rent_contracts')->where('company_id', $companyId)->where('status', 'active')->whereDate('effective_from', '<=', $end)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->groupBy('branch_id')->selectRaw('branch_id, SUM(monthly_amount_minor) total')->pluck('total', 'branch_id');
        $assets = $this->db->table('branch_assets')->where('company_id', $companyId)->where('status', 'active')->whereDate('acquired_on', '<=', $end)->get(['branch_id', 'acquisition_cost_minor', 'useful_life_months']);
        $depreciation = [];
        foreach ($assets as $asset) {
            $branch = $this->integer($asset, 'branch_id');
            $life = max(1, $this->integer($asset, 'useful_life_months'));
            $depreciation[$branch] = ($depreciation[$branch] ?? 0) + intdiv($this->integer($asset, 'acquisition_cost_minor'), $life);
        }
        $payroll = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->join('payroll_employee_inputs as pi', function (JoinClause $join): void {
            $join->on('pi.employee_id', '=', 'i.employee_id')->on('pi.payroll_input_snapshot_id', '=', 'r.payroll_input_snapshot_id');
        })
            ->where('i.company_id', $companyId)->whereNotNull('pi.branch_id')->whereIn('r.status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid'])->where('r.period_start', '<=', $end)->where('r.period_end', '>=', $start)->groupBy('pi.branch_id')->selectRaw('pi.branch_id, SUM(i.gross_amount_minor + i.employer_contribution_amount_minor) total')->pluck('total', 'pi.branch_id');
        $bonuses = $this->db->table('branch_bonus_proposals')->where('company_id', $companyId)->where('status', 'approved')->whereDate('effective_period', $start)->groupBy('branch_id')->selectRaw('branch_id, SUM(amount_minor) total')->pluck('total', 'branch_id');
        $rows = [];
        $totals = ['revenue' => 0, 'reversals' => 0, 'revenue_records' => 0, 'synthetic_revenue_records' => 0, 'payroll' => 0, 'operating' => 0, 'rent' => 0, 'bonus' => 0, 'depreciation' => 0, 'profit' => 0];
        foreach ($branches as $branch) {
            $id = $this->integer($branch, 'id');
            $revenueSource = $revenues->get($id);
            $reversal = $this->objectInteger($reversals->get($id), 'total');
            $revenue = $this->objectInteger($revenueSource, 'total') - $reversal;
            $revenueRecords = $this->objectInteger($revenueSource, 'source_count');
            $syntheticRevenueRecords = $this->objectInteger($revenueSource, 'synthetic_count');
            $revenueSourceTypes = $this->objectText($revenueSource, 'source_types');
            $payrollCost = $this->numericValue($payroll->get($id));
            $operating = $this->numericValue($expenses->get($id));
            $rent = $this->numericValue($rents->get($id));
            $bonus = $this->numericValue($bonuses->get($id));
            $assetCost = (int) ($depreciation[$id] ?? 0);
            // Approved bonus is already an earning component inside Payroll gross.
            // Keep it as a disclosure column, but never subtract it a second time.
            $profit = $revenue - $payrollCost - $operating - $rent - $assetCost;
            $rows[] = ['branch_id' => $id, 'branch' => $this->text($branch, 'name'), 'code' => $this->text($branch, 'code'), 'revenue' => $revenue, 'reversals' => $reversal, 'revenue_records' => $revenueRecords, 'synthetic_revenue_records' => $syntheticRevenueRecords, 'revenue_source_types' => $revenueSourceTypes, 'payroll' => $payrollCost, 'operating' => $operating, 'rent' => $rent, 'bonus' => $bonus, 'depreciation' => $assetCost, 'profit' => $profit];
            foreach (['revenue' => $revenue, 'reversals' => $reversal, 'revenue_records' => $revenueRecords, 'synthetic_revenue_records' => $syntheticRevenueRecords, 'payroll' => $payrollCost, 'operating' => $operating, 'rent' => $rent, 'bonus' => $bonus, 'depreciation' => $assetCost, 'profit' => $profit] as $key => $value) {
                $totals[$key] += $value;
            }
        }

        return ['month' => $start->format('Y-m'), 'rows' => $rows, 'totals' => $totals];
    }

    private function integer(object $row, string $key): int
    {
        return $this->numericValue($row->{$key} ?? null);
    }

    private function numericValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function objectInteger(?object $row, string $key): int
    {
        return $this->numericValue($row?->{$key});
    }

    private function objectText(?object $row, string $key): string
    {
        $value = $row?->{$key};

        return is_string($value) ? $value : '';
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
