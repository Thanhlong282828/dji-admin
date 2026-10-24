<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class FinanceOverviewReadService
{
    public function __construct(private ConnectionInterface $db, private FinancialStatementReadService $statements, private BudgetExposureService $budgetExposure) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, ?string $dateFrom, ?string $dateTo): array
    {
        $statement = $this->statements->report($companyId, $dateFrom, $dateTo, null, 1, $branchId);
        $income = $this->array($statement, 'income_statement');
        $balance = $this->array($statement, 'balance_sheet');
        $cashFlow = $this->array($statement, 'cash_flow');
        $coverage = $this->array($statement, 'coverage');
        $totals = $this->array($statement, 'totals');
        $invoices = $this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid', 'paid'])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->when($dateFrom, fn (Builder $q): Builder => $q->whereDate('invoice_date', '>=', $dateFrom))->when($dateTo, fn (Builder $q): Builder => $q->whereDate('invoice_date', '<=', $dateTo));
        $apAllocations = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id');
        $bills = $this->db->table('vendor_bills as b')->leftJoinSub($apAllocations, 'a', 'a.vendor_bill_id', '=', 'b.id')->where('b.company_id', $companyId)->whereIn('b.status', ['approved', 'partially_paid', 'paid'])->when($branchId, fn (Builder $q): Builder => $q->where('b.branch_id', $branchId))->when($dateFrom, fn (Builder $q): Builder => $q->whereDate('b.invoice_date', '>=', $dateFrom))->when($dateTo, fn (Builder $q): Builder => $q->whereDate('b.invoice_date', '<=', $dateTo));
        $ar = $this->integer((clone $invoices)->selectRaw('COALESCE(SUM(total_minor-paid_minor),0) aggregate')->value('aggregate'));
        $ap = $this->integer((clone $bills)->selectRaw('COALESCE(SUM(b.total_minor-COALESCE(a.allocated,0)),0) aggregate')->value('aggregate'));

        $budget = $this->db->table('finance_budget_lines as l')->join('finance_budgets as b', 'b.id', '=', 'l.budget_id')->where(['b.company_id' => $companyId, 'b.status' => 'approved'])->when($branchId, fn (Builder $q): Builder => $q->where('l.branch_id', $branchId));
        if ($dateFrom !== null) {
            $from = CarbonImmutable::parse($dateFrom);
            $budget->where(fn (Builder $q): Builder => $q->where('b.year', '>', $from->year)->orWhere(fn (Builder $inner): Builder => $inner->where('b.year', $from->year)->where('l.month', '>=', $from->month)));
        }
        if ($dateTo !== null) {
            $to = CarbonImmutable::parse($dateTo);
            $budget->where(fn (Builder $q): Builder => $q->where('b.year', '<', $to->year)->orWhere(fn (Builder $inner): Builder => $inner->where('b.year', $to->year)->where('l.month', '<=', $to->month)));
        }
        $budgetAggregate = $budget->selectRaw('COUNT(*) line_count, COALESCE(SUM(l.amount_minor),0) amount')->first();
        $budgetTotal = $this->integer($budgetAggregate?->amount);
        $commitments = $this->integer($this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')->where('po.company_id', $companyId)->whereIn('po.status', ['approved', 'sent', 'partially_received'])->when($branchId, fn (Builder $q): Builder => $q->where('po.branch_id', $branchId))->selectRaw('COALESCE(SUM((l.ordered_quantity-l.received_quantity)*l.unit_cost_minor),0) aggregate')->value('aggregate'));
        $actual = $this->integer($income['expense'] ?? null);
        $exposure = $this->budgetExposure->calculate($budgetTotal, $actual, $commitments, $this->integer($budgetAggregate?->line_count) > 0);

        return [
            'branches' => $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'income' => $income,
            'balance' => $balance,
            'cash_flow' => $cashFlow,
            'coverage' => $coverage,
            'ar_outstanding' => $ar,
            'ap_outstanding' => $ap,
            'aging' => ['ar' => $this->arAging($companyId, $branchId), 'ap' => $this->apAging($companyId, $branchId)],
            'budget' => ['approved' => $budgetTotal, 'commitment' => $commitments, 'actual' => $actual, 'consumed' => $exposure['consumed'], 'remaining' => $exposure['remaining'], 'status' => $exposure['status']],
            'forecast' => $this->forecast($companyId, $branchId),
            'trial_difference' => $this->integer($totals['difference'] ?? null),
        ];
    }

    /** @return array<string,int> */
    private function arAging(int $companyId, ?int $branchId): array
    {
        $q = $this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid'])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId));

        return $this->agingBuckets($q, 'due_date', 'total_minor-paid_minor');
    }

    /** @return array<string,int> */
    private function apAging(int $companyId, ?int $branchId): array
    {
        $allocations = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id');
        $q = $this->db->table('vendor_bills as b')->leftJoinSub($allocations, 'a', 'a.vendor_bill_id', '=', 'b.id')->where('b.company_id', $companyId)->whereIn('b.status', ['approved', 'partially_paid'])->when($branchId, fn (Builder $q): Builder => $q->where('b.branch_id', $branchId));

        return $this->agingBuckets($q, 'b.due_date', 'b.total_minor-COALESCE(a.allocated,0)');
    }

    /** @return array<string,int> */
    private function agingBuckets(Builder $query, string $date, string $amount): array
    {
        $today = CarbonImmutable::today();
        $ranges = ['current' => [$today, null], '1_30' => [$today->subDays(30), $today->subDay()], '31_60' => [$today->subDays(60), $today->subDays(31)], '61_90' => [$today->subDays(90), $today->subDays(61)], '90_plus' => [null, $today->subDays(91)]];
        $result = [];
        foreach ($ranges as $key => [$from, $to]) {
            $bucket = clone $query;
            if ($from !== null) {
                $bucket->whereDate($date, '>=', $from->toDateString());
            }
            if ($to !== null) {
                $bucket->whereDate($date, '<=', $to->toDateString());
            }
            $result[$key] = $this->integer(match ($amount) {
                'total_minor-paid_minor' => $bucket->selectRaw('COALESCE(SUM(total_minor-paid_minor),0) aggregate')->value('aggregate'),
                default => $bucket->selectRaw('COALESCE(SUM(b.total_minor-COALESCE(a.allocated,0)),0) aggregate')->value('aggregate'),
            });
        }

        return $result;
    }

    /** @return array<int,array{days:int,receivable:int,payable:int,net:int}> */
    private function forecast(int $companyId, ?int $branchId): array
    {
        $today = CarbonImmutable::today();
        $rows = [];
        foreach ([7, 30, 60, 90] as $days) {
            $end = $today->addDays($days)->toDateString();
            $receivable = $this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid'])->whereBetween('due_date', [$today->toDateString(), $end])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->selectRaw('COALESCE(SUM(total_minor-paid_minor),0) aggregate')->value('aggregate');
            $allocations = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id');
            $payable = $this->db->table('vendor_bills as b')->leftJoinSub($allocations, 'a', 'a.vendor_bill_id', '=', 'b.id')->where('b.company_id', $companyId)->whereIn('b.status', ['approved', 'partially_paid'])->whereBetween('b.due_date', [$today->toDateString(), $end])->when($branchId, fn (Builder $q): Builder => $q->where('b.branch_id', $branchId))->selectRaw('COALESCE(SUM(b.total_minor-COALESCE(a.allocated,0)),0) aggregate')->value('aggregate');
            $in = $this->integer($receivable);
            $out = $this->integer($payable);
            $rows[] = ['days' => $days, 'receivable' => $in, 'payable' => $out, 'net' => $in - $out];
        }

        return $rows;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string,mixed>  $source
     * @return array<string,mixed>
     */
    private function array(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (! is_array($value)) {
            return [];
        }
        $normalized = [];
        foreach ($value as $nestedKey => $nestedValue) {
            if (is_string($nestedKey)) {
                $normalized[$nestedKey] = $nestedValue;
            }
        }

        return $normalized;
    }
}
