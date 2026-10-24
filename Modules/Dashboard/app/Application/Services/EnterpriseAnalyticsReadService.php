<?php

namespace Modules\Dashboard\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Commerce\Application\Services\CustomerMetricsService;
use Modules\Finance\Application\Services\FinanceOverviewReadService;

final readonly class EnterpriseAnalyticsReadService
{
    public function __construct(private ConnectionInterface $db, private FinanceOverviewReadService $finance, private SupplyChainOverviewReadService $supplyChain, private CustomerMetricsService $customers, private EnterpriseKpiCatalog $catalog) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, string $dateFrom, string $dateTo): array
    {
        $to = CarbonImmutable::parse($dateTo)->endOfMonth();
        $from = CarbonImmutable::parse($dateFrom)->startOfMonth();
        $months = $this->months($from, $to);
        $this->merge($months, $this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid', 'paid'])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->selectRaw("DATE_FORMAT(invoice_date,'%Y-%m') month, SUM(total_minor) amount")->groupBy('month')->get(), 'erp_sales');
        foreach (['commerce_order' => 'marketplace', 'branch_pos' => 'pos'] as $source => $key) {
            $this->merge($months, $this->db->table('branch_management_revenues')->where(['company_id' => $companyId, 'source_type' => $source, 'status' => 'recognized'])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->whereBetween('recognized_on', [$from->toDateString(), $to->toDateString()])->selectRaw("DATE_FORMAT(recognized_on,'%Y-%m') month, SUM(amount_minor) amount")->groupBy('month')->get(), $key);
        }
        $this->merge($months, $this->db->table('payroll_runs')->where('company_id', $companyId)->where('status', 'finalized')->whereBetween('period_end', [$from->toDateString(), $to->toDateString()])->selectRaw("DATE_FORMAT(period_end,'%Y-%m') month, SUM(gross_total_minor + employer_contribution_total_minor) amount")->groupBy('month')->get(), 'payroll');
        $this->merge($months, $this->db->table('finance_expenses')->where('company_id', $companyId)->whereIn('status', ['approved', 'paid'])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->selectRaw("DATE_FORMAT(expense_date,'%Y-%m') month, SUM(amount_minor) amount")->groupBy('month')->get(), 'opex');
        foreach (['in' => 'cash_in', 'out' => 'cash_out'] as $direction => $key) {
            $this->merge($months, $this->db->table('treasury_transactions')->where(['company_id' => $companyId, 'direction' => $direction, 'status' => 'recorded'])->where('source_type', '<>', 'treasury_internal_transfer_leg')->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])->selectRaw("DATE_FORMAT(occurred_at,'%Y-%m') month, SUM(amount_minor) amount")->groupBy('month')->get(), $key);
        }
        foreach ($months as &$row) {
            $row['revenue'] = $this->integer($row['erp_sales']) + $this->integer($row['marketplace']) + $this->integer($row['pos']);
        }
        unset($row);
        $finance = $this->finance->read($companyId, $branchId, $from->toDateString(), $to->toDateString());
        $scm = $this->supplyChain->read($companyId);
        $crm = $this->customers->forCompany($companyId);
        $headcount = $this->db->table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation', 'leave'])->whereNull('deleted_at')->count();
        $late = $this->db->table('attendance_records')->where('company_id', $companyId)->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])->where('late_minutes', '>', 0)->count();
        $ot = (int) $this->db->table('attendance_records')->where('company_id', $companyId)->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])->sum('overtime_minutes');
        $series = $this->series($months);
        $current = $series[array_key_last($series)] ?? null;
        $previous = count($series) > 1 ? $series[count($series) - 2] : null;
        $currentRevenue = is_array($current) ? $this->integer($current['revenue'] ?? null) : 0;
        $previousRevenue = is_array($previous) ? $this->integer($previous['revenue'] ?? null) : 0;
        $variance = $currentRevenue - $previousRevenue;

        return ['filters' => compact('companyId', 'branchId', 'dateFrom', 'dateTo'), 'catalog' => $this->catalog->definitions(), 'series' => $series, 'period' => ['current' => $current, 'previous' => $previous, 'revenue_variance' => $variance, 'revenue_variance_bp' => $previousRevenue === 0 ? null : intdiv($variance * 10_000, $previousRevenue)], 'finance' => $finance, 'supply_chain' => $scm, 'people' => ['active_headcount' => $headcount, 'late_records' => $late, 'overtime_minutes' => $ot], 'crm' => ['customers' => $crm->count(), 'new' => $crm->where('segment', 'NEW')->count(), 'active' => $crm->where('segment', 'ACTIVE')->count(), 'repeat' => $crm->where('segment', 'REPEAT')->count(), 'at_risk' => $crm->where('segment', 'AT_RISK')->count(), 'dormant' => $crm->where('segment', 'DORMANT')->count()], 'quality' => ['status' => 'ON_DEMAND', 'route' => 'admin.operations.data-quality'], 'forecast' => $finance['forecast'] ?? [], 'limitations' => ['inventory_trend' => 'NOT_ENOUGH_EVIDENCE', 'stock_turnover' => 'NOT_ENOUGH_EVIDENCE', 'future_revenue' => 'NOT_ENOUGH_EVIDENCE']];
    }

    /**
     * @param  array<string,array<string,int|string>>  $months
     * @param  iterable<object>  $rows
     */
    private function merge(array &$months, iterable $rows, string $key): void
    {
        foreach ($rows as $row) {
            $monthValue = data_get($row, 'month');
            $month = is_string($monthValue) ? $monthValue : '';
            if (isset($months[$month])) {
                $months[$month][$key] = $this->integer(data_get($row, 'amount'));
            }
        }
    }

    /**
     * @param  array<string,array<string,int|string>>  $months
     * @return list<array<string,int|string>>
     */
    private function series(array $months): array
    {
        return array_values($months);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return array<string,array<string,int|string>> */
    private function months(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];
        for ($cursor = $from; $cursor <= $to; $cursor = $cursor->addMonth()) {
            $months[$cursor->format('Y-m')] = ['month' => $cursor->format('Y-m'), 'erp_sales' => 0, 'marketplace' => 0, 'pos' => 0, 'revenue' => 0, 'payroll' => 0, 'opex' => 0, 'cash_in' => 0, 'cash_out' => 0];
        }

        return $months;
    }
}
