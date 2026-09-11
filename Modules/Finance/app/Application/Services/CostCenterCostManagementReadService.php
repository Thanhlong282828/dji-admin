<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * @phpstan-type CostRow array{cost_center_id:int,code:string,name:string,budget:int,commitment:?int,actual:int,recognized_expense:int,consumed:int,remaining:int,variance_bp:?int,payroll:int,employer_statutory:int,inventory_acquisition:int,depreciation:int,project:int,maintenance:int,other_opex:int,cash_paid:int,status:string}
 * @phpstan-type CostManagementResult array{filters:array{date_from:string,date_to:string,cost_center_id:?int},cost_centers:\Illuminate\Support\Collection<int,\stdClass>,rows:list<CostRow>,totals:array{budget:int,commitment:int,actual:int,recognized_expense:int,inventory_acquisition:int,cash_paid:int,consumed:int,remaining:int,over_budget:int,unattributed_actual:int,unattributed_commitment:int,legacy_payroll_unattributed:int,coverage_bp:int},reconciliation:array{company_expense:int,attributed:int,unattributed:int,difference:int,status:string},evidence:array{revenue:string,cogs:string,payroll_future:string,payroll_legacy:string,commitment:string,formula:string,recognized_expense:string,budget_actual:string,cash:string}}
 */
final readonly class CostCenterCostManagementReadService
{
    private const POSTED = ['internally_posted', 'exported'];

    public function __construct(private ConnectionInterface $db, private BudgetExposureService $budgetExposure) {}

    /** @return CostManagementResult */
    public function read(int $companyId, ?int $costCenterId, string $dateFrom, string $dateTo): array
    {
        $from = CarbonImmutable::parse($dateFrom);
        $to = CarbonImmutable::parse($dateTo);
        $centers = $this->db->table('cost_centers')->where('company_id', $companyId)
            ->when($costCenterId, fn (Builder $query): Builder => $query->where('id', $costCenterId))
            ->orderBy('name')->get(['id', 'code', 'name']);

        $budgets = $this->db->table('finance_budget_lines as l')->join('finance_budgets as b', 'b.id', '=', 'l.budget_id')
            ->where(['b.company_id' => $companyId, 'b.status' => 'approved'])->whereNotNull('l.cost_center_id')
            ->when($costCenterId, fn (Builder $query): Builder => $query->where('l.cost_center_id', $costCenterId))
            ->where(fn (Builder $query): Builder => $query->where('b.year', '>', $from->year)->orWhere(fn (Builder $inner): Builder => $inner->where('b.year', $from->year)->where('l.month', '>=', $from->month)))
            ->where(fn (Builder $query): Builder => $query->where('b.year', '<', $to->year)->orWhere(fn (Builder $inner): Builder => $inner->where('b.year', $to->year)->where('l.month', '<=', $to->month)))
            ->selectRaw('l.cost_center_id, SUM(l.amount_minor) amount')->groupBy('l.cost_center_id')->pluck('amount', 'cost_center_id');

        $cashPaidQuery = $this->db->table('treasury_transactions as t')
            ->join('finance_expenses as e', function (JoinClause $join): void {
                $join->on('e.id', '=', 't.source_id')->where('t.source_type', '=', 'finance_expense_payment');
            })
            ->where('t.company_id', $companyId)->where('t.direction', 'outflow')->whereBetween('t.occurred_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->whereNotNull('e.cost_center_id')->selectRaw('e.cost_center_id, SUM(t.amount_minor) amount')->groupBy('e.cost_center_id');

        $actuals = $this->budgetActualLines($companyId, $dateFrom, $dateTo)
            ->leftJoinSub($cashPaidQuery, 'cash', 'cash.cost_center_id', '=', 'l.cost_center_id')
            ->whereNotNull('l.cost_center_id')
            ->when($costCenterId, fn (Builder $query): Builder => $query->where('l.cost_center_id', $costCenterId))
            ->selectRaw("l.cost_center_id,
                SUM(l.debit_minor-l.credit_minor) budget_actual,
                SUM(CASE WHEN a.account_type='expense' THEN l.debit_minor-l.credit_minor ELSE 0 END) recognized_expense,
                SUM(CASE WHEN l.source_type='earning_component' THEN l.debit_minor-l.credit_minor ELSE 0 END) payroll,
                SUM(CASE WHEN l.source_type='employer_insurance_expense' THEN l.debit_minor-l.credit_minor ELSE 0 END) employer_statutory,
                SUM(CASE WHEN l.source_type='goods_receipt' AND l.account_code_snapshot='1200' THEN l.debit_minor-l.credit_minor ELSE 0 END) inventory_acquisition,
                SUM(CASE WHEN l.account_code_snapshot='5200' OR l.source_type LIKE 'depreciation%' THEN l.debit_minor-l.credit_minor ELSE 0 END) depreciation,
                SUM(CASE WHEN fe.source_type='PROJECT' THEN l.debit_minor-l.credit_minor ELSE 0 END) project_cost,
                SUM(CASE WHEN fe.source_type='MAINTENANCE' THEN l.debit_minor-l.credit_minor ELSE 0 END) maintenance_cost,
                COALESCE(MAX(cash.amount),0) cash_paid")
            ->groupBy('l.cost_center_id')->get()->keyBy('cost_center_id');

        // Some historical payroll journals were dimensioned from the employee's
        // then-current assignment before immutable Cost Center input snapshots
        // existed. Keep those source amounts out of attributed Actual instead of
        // presenting a reconstructed dimension as historical evidence.
        $legacyPayrollByCenter = $this->legacyPayrollByCostCenter($companyId, $dateFrom, $dateTo);

        $openCommitments = $this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')
            ->where('po.company_id', $companyId)->whereIn('po.status', ['approved', 'sent', 'partially_received'])
            ->whereBetween('po.order_date', [$dateFrom, $dateTo])
            ->whereNotNull('l.cost_center_id')
            ->when($costCenterId, fn (Builder $query): Builder => $query->where('l.cost_center_id', $costCenterId))
            ->selectRaw('l.cost_center_id, SUM((l.ordered_quantity-l.received_quantity)*l.unit_cost_minor) amount')
            ->groupBy('l.cost_center_id')->pluck('amount', 'cost_center_id');
        $unattributedCommitment = $this->integer($this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')
            ->where('po.company_id', $companyId)->whereIn('po.status', ['approved', 'sent', 'partially_received'])->whereBetween('po.order_date', [$dateFrom, $dateTo])->whereNull('l.cost_center_id')
            ->selectRaw('COALESCE(SUM((l.ordered_quantity-l.received_quantity)*l.unit_cost_minor),0) aggregate')->value('aggregate'));

        $rows = [];
        foreach ($centers as $center) {
            $id = $this->integer($center->id ?? null);
            $budget = $this->integer($budgets->get($id));
            $source = $actuals->get($id);
            $legacy = $legacyPayrollByCenter->get($id);
            $legacyPayroll = $this->field($legacy, 'payroll');
            $legacyStatutory = $this->field($legacy, 'employer_statutory');
            $actual = $this->field($source, 'budget_actual') - $legacyPayroll - $legacyStatutory;
            $recognizedExpense = $this->field($source, 'recognized_expense') - $legacyPayroll - $legacyStatutory;
            $payroll = $this->field($source, 'payroll') - $legacyPayroll;
            $statutory = $this->field($source, 'employer_statutory') - $legacyStatutory;
            $inventory = $this->field($source, 'inventory_acquisition');
            $depreciation = $this->field($source, 'depreciation');
            $project = $this->field($source, 'project_cost');
            $maintenance = $this->field($source, 'maintenance_cost');
            $other = $recognizedExpense - $payroll - $statutory - $depreciation - $project - $maintenance;
            $knownCommitment = $this->integer($openCommitments->get($id));
            $exposure = $this->budgetExposure->calculate($budget, $actual, $knownCommitment, $budgets->has($id));
            $remaining = $exposure['remaining'];
            $status = match (true) {
                $unattributedCommitment > 0 => 'PARTIAL_EVIDENCE',
                default => $exposure['status'],
            };
            $rows[] = ['cost_center_id' => $id, 'code' => $this->text($center->code ?? null), 'name' => $this->text($center->name ?? null), 'budget' => $budget, 'commitment' => $knownCommitment, 'actual' => $actual, 'recognized_expense' => $recognizedExpense, 'consumed' => $exposure['consumed'], 'remaining' => $remaining, 'variance_bp' => $budget === 0 ? null : intdiv($remaining * 10_000, $budget), 'payroll' => $payroll, 'employer_statutory' => $statutory, 'inventory_acquisition' => $inventory, 'depreciation' => $depreciation, 'project' => $project, 'maintenance' => $maintenance, 'other_opex' => $other, 'cash_paid' => $this->field($source, 'cash_paid'), 'status' => $status];
        }

        $attributedActual = array_sum(array_column($rows, 'recognized_expense'));
        $budgetActual = array_sum(array_column($rows, 'actual'));
        $companyActual = $this->integer($this->expenseLines($companyId, $dateFrom, $dateTo)->sum($this->db->raw('l.debit_minor-l.credit_minor')));
        $unattributedActual = $companyActual - $attributedActual;
        $legacyPayroll = $this->legacyPayrollUnattributed($companyId, $dateFrom, $dateTo);
        $totalBudget = array_sum(array_column($rows, 'budget'));
        $totalCommitment = array_sum(array_column($rows, 'commitment'));

        $totalExposure = $this->budgetExposure->calculate($totalBudget, $budgetActual, $totalCommitment, $budgets->isNotEmpty());
        $coverageBasis = max(0, $companyActual);
        $coverageBp = $coverageBasis === 0 ? 10_000 : min(10_000, intdiv(max(0, $attributedActual) * 10_000, $coverageBasis));

        return [
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'cost_center_id' => $costCenterId],
            'cost_centers' => $centers,
            'rows' => $rows,
            'totals' => ['budget' => $totalBudget, 'commitment' => $totalCommitment, 'actual' => $budgetActual, 'recognized_expense' => $attributedActual, 'inventory_acquisition' => array_sum(array_column($rows, 'inventory_acquisition')), 'cash_paid' => array_sum(array_column($rows, 'cash_paid')), 'consumed' => $totalExposure['consumed'], 'remaining' => $totalExposure['remaining'], 'over_budget' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'OVER_BUDGET')), 'unattributed_actual' => $unattributedActual, 'unattributed_commitment' => $unattributedCommitment, 'legacy_payroll_unattributed' => $legacyPayroll, 'coverage_bp' => $coverageBp],
            'reconciliation' => ['company_expense' => $companyActual, 'attributed' => $attributedActual, 'unattributed' => $unattributedActual, 'difference' => $companyActual - $attributedActual - $unattributedActual, 'status' => $unattributedActual === 0 && $unattributedCommitment === 0 ? 'PASS' : 'PARTIAL'],
            'evidence' => ['revenue' => 'NOT_ENOUGH_EVIDENCE', 'cogs' => 'NOT_ENOUGH_EVIDENCE', 'payroll_future' => 'PASS', 'payroll_legacy' => 'NOT_ENOUGH_EVIDENCE', 'commitment' => $unattributedCommitment === 0 ? 'PASS' : 'PARTIAL', 'formula' => 'Remaining = Approved Budget - Budget Actual - Open Commitment', 'recognized_expense' => 'Posted expense-account journal lines only', 'budget_actual' => 'Recognized expense plus inventory procurement realization; never AP/payment', 'cash' => 'Treasury outflow shown separately; cash is not expense'],
        ];
    }

    private function budgetActualLines(int $companyId, string $dateFrom, string $dateTo): Builder
    {
        return $this->db->table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->leftJoin('finance_expenses as fe', function (JoinClause $join): void {
                $join->on('fe.id', '=', 'j.source_id')->where('j.source_type', '=', 'finance_expense');
            })
            ->where('j.company_id', $companyId)->where('l.company_id', $companyId)
            ->where(fn (Builder $query): Builder => $query->where('a.account_type', 'expense')->orWhere(fn (Builder $inventory): Builder => $inventory->where('l.source_type', 'goods_receipt')->where('l.account_code_snapshot', '1200')->whereNotNull('l.cost_center_id')))
            ->whereIn('j.status', self::POSTED)->whereBetween('j.posting_date', [$dateFrom, $dateTo]);
    }

    private function expenseLines(int $companyId, string $dateFrom, string $dateTo): Builder
    {
        return $this->db->table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $companyId)->where('l.company_id', $companyId)
            ->where('a.account_type', 'expense')
            ->whereIn('j.status', self::POSTED)->whereBetween('j.posting_date', [$dateFrom, $dateTo]);
    }

    private function legacyPayrollUnattributed(int $companyId, string $dateFrom, string $dateTo): int
    {
        return $this->integer($this->db->table('payroll_accounting_journal_sources as s')->join('payroll_accounting_journal_lines as l', 'l.id', '=', 's.journal_line_id')->join('payroll_accounting_journals as j', 'j.id', '=', 's.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->join('payroll_runs as r', 'r.id', '=', 'j.payroll_run_id')->join('payroll_employee_inputs as i', function (JoinClause $join): void {
            $join->on('i.payroll_input_snapshot_id', '=', 'r.payroll_input_snapshot_id')->on('i.employee_id', '=', 's.employee_id');
        })->where('j.company_id', $companyId)->whereNull('i.cost_center_id')->whereIn('j.status', self::POSTED)->where('a.account_type', 'expense')->whereIn('l.source_type', ['earning_component', 'employer_insurance_expense'])->where('l.debit_minor', '>', 0)->whereBetween('j.posting_date', [$dateFrom, $dateTo])->sum('s.source_amount_minor'));
    }

    /** @return Collection<int|string,\stdClass> */
    private function legacyPayrollByCostCenter(int $companyId, string $dateFrom, string $dateTo): Collection
    {
        return $this->db->table('payroll_accounting_journal_sources as s')->join('payroll_accounting_journal_lines as l', 'l.id', '=', 's.journal_line_id')->join('payroll_accounting_journals as j', 'j.id', '=', 's.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->join('payroll_runs as r', 'r.id', '=', 'j.payroll_run_id')->join('payroll_employee_inputs as i', function (JoinClause $join): void {
            $join->on('i.payroll_input_snapshot_id', '=', 'r.payroll_input_snapshot_id')->on('i.employee_id', '=', 's.employee_id');
        })->where('j.company_id', $companyId)->whereNull('i.cost_center_id')->whereNotNull('l.cost_center_id')->whereIn('j.status', self::POSTED)->where('a.account_type', 'expense')->whereIn('l.source_type', ['earning_component', 'employer_insurance_expense'])->whereBetween('j.posting_date', [$dateFrom, $dateTo])
            ->selectRaw("l.cost_center_id,
                SUM(CASE WHEN l.source_type='earning_component' THEN s.source_amount_minor ELSE 0 END) payroll,
                SUM(CASE WHEN l.source_type='employer_insurance_expense' THEN s.source_amount_minor ELSE 0 END) employer_statutory")
            ->groupBy('l.cost_center_id')->get()->keyBy('cost_center_id');
    }

    private function field(?object $row, string $field): int
    {
        if ($row === null) {
            return 0;
        }

        return $this->integer($row->{$field} ?? null);
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
