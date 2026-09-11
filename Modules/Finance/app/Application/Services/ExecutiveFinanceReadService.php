<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

final readonly class ExecutiveFinanceReadService
{
    private const POSTED = ['internally_posted', 'exported'];

    public function __construct(private ConnectionInterface $db, private BudgetExposureService $budgetExposure) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, string $dateFrom, string $dateTo): array
    {
        return [
            'branch_profitability' => $this->branchProfitability($companyId, $branchId, $dateFrom, $dateTo),
            'budget' => $this->budget($companyId, $branchId, $dateFrom, $dateTo),
            'outlook' => $this->outlook($companyId, $branchId),
        ];
    }

    /** @return array{status:string,rows:list<array<string,int|string>>,unattributed:int} */
    private function branchProfitability(int $companyId, ?int $branchId, string $from, string $to): array
    {
        $lines = $this->db->table('payroll_accounting_journal_lines as l')
            ->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $companyId)->where('l.company_id', $companyId)
            ->whereIn('j.status', self::POSTED)->whereBetween('j.posting_date', [$from, $to])
            ->where(fn (Builder $q): Builder => $q->whereNotNull('l.branch_id')->orWhereNull('j.payroll_run_id'))
            ->when($branchId, fn (Builder $q): Builder => $q->whereRaw('COALESCE(l.branch_id,j.branch_id)=?', [$branchId]))
            ->selectRaw("COALESCE(l.branch_id,j.branch_id) branch_id,
                SUM(CASE WHEN a.account_type IN ('revenue','income') THEN l.credit_minor-l.debit_minor ELSE 0 END) revenue,
                SUM(CASE WHEN a.account_type='expense' AND a.code='5000' THEN l.debit_minor-l.credit_minor ELSE 0 END) cogs,
                SUM(CASE WHEN a.account_type='expense' AND (a.code LIKE '6421%' OR l.source_type IN ('earning_component','employer_insurance_expense')) THEN l.debit_minor-l.credit_minor ELSE 0 END) payroll,
                SUM(CASE WHEN a.account_type='expense' AND l.source_type='employer_insurance_expense' THEN l.debit_minor-l.credit_minor ELSE 0 END) employer_statutory,
                SUM(CASE WHEN a.account_type='expense' AND a.code='5200' THEN l.debit_minor-l.credit_minor ELSE 0 END) depreciation,
                SUM(CASE WHEN a.account_type='expense' AND l.source_type LIKE 'maintenance%' THEN l.debit_minor-l.credit_minor ELSE 0 END) maintenance,
                SUM(CASE WHEN a.account_type='expense' AND l.source_type LIKE 'project%' THEN l.debit_minor-l.credit_minor ELSE 0 END) project,
                SUM(CASE WHEN a.account_type='expense' AND a.code NOT IN ('5000','5200') AND a.code NOT LIKE '6421%' AND l.source_type NOT IN ('earning_component','employer_insurance_expense') THEN l.debit_minor-l.credit_minor ELSE 0 END) other_opex")
            ->groupByRaw('COALESCE(l.branch_id,j.branch_id)')->get()->keyBy('branch_id');
        $legacyPayroll = $this->legacyPayrollDimensions($companyId, $branchId, $from, $to);
        $branches = $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')
            ->when($branchId, fn (Builder $q): Builder => $q->where('id', $branchId))->orderBy('name')->get(['id', 'name']);
        $rows = [];
        foreach ($branches as $branch) {
            $id = $this->field($branch, 'id');
            $source = $lines->get($id);
            $revenue = $this->field($source, 'revenue');
            $cogs = $this->field($source, 'cogs');
            $legacy = $legacyPayroll->get($id);
            $payroll = $this->field($source, 'payroll') + $this->field($legacy, 'payroll');
            $employerStatutory = $this->field($source, 'employer_statutory') + $this->field($legacy, 'employer_statutory');
            $depreciation = $this->field($source, 'depreciation');
            $maintenance = $this->field($source, 'maintenance');
            $project = $this->field($source, 'project');
            $other = $this->field($source, 'other_opex');
            $gross = $revenue - $cogs;
            $rows[] = ['branch_id' => $id, 'branch' => $this->text($branch, 'name'), 'revenue' => $revenue, 'cogs' => $cogs, 'gross_profit' => $gross, 'payroll' => $payroll, 'employer_statutory' => $employerStatutory, 'depreciation' => $depreciation, 'maintenance' => $maintenance, 'project' => $project, 'other_opex' => $other, 'opex' => $payroll + $depreciation + $other, 'operating_result' => $gross - $payroll - $depreciation - $other];
        }
        $missing = $lines->get(null);
        $legacyMissing = $legacyPayroll->get(null);
        $unattributed = abs($this->field($missing, 'revenue')) + abs($this->field($missing, 'cogs')) + abs($this->field($missing, 'payroll')) + abs($this->field($missing, 'depreciation')) + abs($this->field($missing, 'other_opex')) + abs($this->field($legacyMissing, 'payroll'));

        return ['status' => $unattributed === 0 ? 'PASS' : 'PARTIAL', 'rows' => $rows, 'unattributed' => $unattributed];
    }

    /** @return Collection<int|string,\stdClass> */
    private function legacyPayrollDimensions(int $companyId, ?int $branchId, string $from, string $to): Collection
    {
        return $this->db->table('payroll_accounting_journal_sources as s')
            ->join('payroll_accounting_journal_lines as l', 'l.id', '=', 's.journal_line_id')
            ->join('payroll_accounting_journals as j', 'j.id', '=', 's.journal_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('payroll_runs as r', 'r.id', '=', 'j.payroll_run_id')
            ->join('payroll_employee_inputs as i', function (JoinClause $join): void {
                $join->on('i.payroll_input_snapshot_id', '=', 'r.payroll_input_snapshot_id')->on('i.employee_id', '=', 's.employee_id');
            })
            ->where('j.company_id', $companyId)->whereNull('l.branch_id')->whereNotNull('s.employee_id')
            ->whereIn('j.status', self::POSTED)->whereBetween('j.posting_date', [$from, $to])
            ->when($branchId, fn (Builder $q): Builder => $q->where('i.branch_id', $branchId))
            ->selectRaw("i.branch_id,
                SUM(CASE WHEN a.account_type='expense' AND l.source_type IN ('earning_component','employer_insurance_expense') AND l.debit_minor>0 THEN s.source_amount_minor ELSE 0 END) payroll,
                SUM(CASE WHEN a.account_type='expense' AND l.source_type='employer_insurance_expense' AND l.debit_minor>0 THEN s.source_amount_minor ELSE 0 END) employer_statutory")
            ->groupBy('i.branch_id')->get()->keyBy('branch_id');
    }

    /** @return array{status:string,formula:string,rows:list<array<string,int|string>>} */
    private function budget(int $companyId, ?int $branchId, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $budgetRows = $this->db->table('finance_budget_lines as l')->join('finance_budgets as b', 'b.id', '=', 'l.budget_id')
            ->leftJoin('branches as br', 'br.id', '=', 'l.branch_id')->where(['b.company_id' => $companyId, 'b.status' => 'approved'])
            ->when($branchId, fn (Builder $q): Builder => $q->where('l.branch_id', $branchId))
            ->whereRaw('(b.year > ? OR (b.year = ? AND l.month >= ?))', [$start->year, $start->year, $start->month])
            ->whereRaw('(b.year < ? OR (b.year = ? AND l.month <= ?))', [$end->year, $end->year, $end->month])
            ->selectRaw("COALESCE(l.branch_id,0) branch_id, COALESCE(br.name,'Toàn công ty') branch, SUM(l.amount_minor) budget")
            ->groupBy('l.branch_id', 'br.name')->get()->keyBy('branch_id');
        $actuals = $this->db->table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $companyId)->whereIn('j.status', self::POSTED)->where('a.account_type', 'expense')->whereBetween('j.posting_date', [$from, $to])
            ->where(fn (Builder $q): Builder => $q->whereNotNull('l.branch_id')->orWhereNull('j.payroll_run_id'))
            ->when($branchId, fn (Builder $q): Builder => $q->whereRaw('COALESCE(l.branch_id,j.branch_id)=?', [$branchId]))->selectRaw('COALESCE(l.branch_id,j.branch_id,0) branch_id, SUM(l.debit_minor-l.credit_minor) actual')->groupByRaw('COALESCE(l.branch_id,j.branch_id,0)')->pluck('actual', 'branch_id');
        foreach ($this->legacyPayrollDimensions($companyId, $branchId, $from, $to) as $legacyBranch => $legacy) {
            $actuals->put($legacyBranch, $this->number($actuals->get($legacyBranch)) + $this->field($legacy, 'payroll'));
        }
        $commitments = $this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')->where('po.company_id', $companyId)->whereIn('po.status', ['approved', 'sent', 'partially_received'])
            ->when($branchId, fn (Builder $q): Builder => $q->where('po.branch_id', $branchId))->selectRaw('COALESCE(po.branch_id,0) branch_id, SUM((l.ordered_quantity-l.received_quantity)*l.unit_cost_minor) commitment')->groupBy('po.branch_id')->pluck('commitment', 'branch_id');
        $keys = $budgetRows->keys()->merge($actuals->keys())->merge($commitments->keys())->unique()->values();
        $branchNames = $this->db->table('branches')->where('company_id', $companyId)->pluck('name', 'id');
        $rows = [];
        $unmatchedEvidence = false;
        foreach ($keys as $key) {
            $normalizedKey = $this->number($key);
            $budgetRow = $budgetRows->get($normalizedKey);
            $budget = $this->field($budgetRow, 'budget');
            $actual = $this->number($actuals->get($normalizedKey));
            $commitment = $this->number($commitments->get($normalizedKey));
            $fallbackName = $branchNames->get($normalizedKey);
            $branchName = $budgetRow === null ? ($normalizedKey === 0 ? 'Toàn công ty' : (is_string($fallbackName) ? $fallbackName : 'Chi nhánh #'.$normalizedKey)) : $this->text($budgetRow, 'branch');
            $unmatchedEvidence = $unmatchedEvidence || ($budgetRow === null && ($actual !== 0 || $commitment !== 0));
            $exposure = $this->budgetExposure->calculate($budget, $actual, $commitment, $budgetRow !== null);
            $rows[] = ['branch_id' => $normalizedKey, 'branch' => $branchName, 'budget' => $budget, 'commitment' => $commitment, 'actual' => $actual, 'consumed' => $exposure['consumed'], 'remaining' => $exposure['remaining'], 'status' => $exposure['status']];
        }

        return ['status' => $budgetRows->isEmpty() ? 'NOT_ENOUGH_EVIDENCE' : ($unmatchedEvidence ? 'PARTIAL' : 'PASS'), 'formula' => 'Remaining = Budget - Actual - open PO Commitment', 'rows' => $rows];
    }

    /** @return array{status:string,buckets:list<array<string,int|string>>,procurement_commitment:int,payroll_future_status:string,projected_closing_cash_status:string} */
    private function outlook(int $companyId, ?int $branchId): array
    {
        $today = CarbonImmutable::today();
        $ranges = [['next_7', '7 ngày tới', 0, 7], ['8_30', '8–30 ngày', 8, 30], ['31_60', '31–60 ngày', 31, 60], ['61_90', '61–90 ngày', 61, 90]];
        $apAllocated = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id');
        $rows = [];
        foreach ($ranges as [$key, $label, $start, $end]) {
            $from = $today->addDays($start)->toDateString();
            $to = $today->addDays($end)->toDateString();
            $ar = $this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid'])->whereBetween('due_date', [$from, $to])->when($branchId, fn (Builder $q): Builder => $q->where('branch_id', $branchId))->sum($this->db->raw('total_minor-paid_minor'));
            $ap = $this->db->table('vendor_bills as b')->leftJoinSub($apAllocated, 'a', 'a.vendor_bill_id', '=', 'b.id')->where('b.company_id', $companyId)->whereIn('b.status', ['approved', 'partially_paid'])->whereBetween('b.due_date', [$from, $to])->when($branchId, fn (Builder $q): Builder => $q->where('b.branch_id', $branchId))->sum($this->db->raw('b.total_minor-COALESCE(a.allocated,0)'));
            $rows[] = ['key' => $key, 'label' => $label, 'receivable' => $this->number($ar), 'payable' => $this->number($ap), 'net' => $this->number($ar) - $this->number($ap)];
        }
        $commitment = $this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')->where('po.company_id', $companyId)->whereIn('po.status', ['approved', 'sent', 'partially_received'])->when($branchId, fn (Builder $q): Builder => $q->where('po.branch_id', $branchId))->sum($this->db->raw('(l.ordered_quantity-l.received_quantity)*l.unit_cost_minor'));

        return ['status' => 'PASS', 'buckets' => $rows, 'procurement_commitment' => $this->number($commitment), 'payroll_future_status' => 'NOT_ENOUGH_EVIDENCE', 'projected_closing_cash_status' => 'NOT_ENOUGH_EVIDENCE'];
    }

    private function field(?object $row, string $field): int
    {
        return $this->number($row?->{$field});
    }

    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) ? $value : '';
    }
}
