<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;

final readonly class PayrollFinanceReconciliationService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{rows:list<array<string,int|string>>,totals:array<string,int>,unreconciled:int} */
    public function report(int $companyId, ?int $periodId = null): array
    {
        $runs = $this->db->table('payroll_runs as r')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')
            ->where('r.company_id', $companyId)->whereIn('r.status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid'])
            ->when($periodId !== null, fn ($query) => $query->where('p.id', $periodId))
            ->orderByDesc('r.period_end')->get(['r.id', 'r.code as run_code', 'r.status', 'r.payroll_input_snapshot_id', 'p.id as period_id', 'p.code as period_code', 'p.name as period_name']);
        $runIds = $runs->map(fn (object $run): int => $this->integer($run, 'id'))->all();
        $itemsByRun = $this->db->table('payroll_items')->where('company_id', $companyId)->whereIn('payroll_run_id', $runIds)
            ->selectRaw('payroll_run_id, COALESCE(SUM(gross_amount_minor),0) gross, COALESCE(SUM(deduction_amount_minor),0) deductions, COALESCE(SUM(employer_contribution_amount_minor),0) employer, COALESCE(SUM(net_amount_minor),0) net, COALESCE(SUM(insurance_employee_total_minor),0) insurance_employee, COALESCE(SUM(insurance_employer_total_minor),0) insurance_employer, COALESCE(SUM(personal_income_tax_minor),0) pit')
            ->groupBy('payroll_run_id')->get()->keyBy('payroll_run_id');
        $bonusesByRun = $this->db->table('payroll_item_components')->where('company_id', $companyId)->whereIn('payroll_run_id', $runIds)->where('component_code_snapshot', 'BRANCH_BONUS')
            ->selectRaw('payroll_run_id, COALESCE(SUM(amount_minor),0) total')->groupBy('payroll_run_id')->pluck('total', 'payroll_run_id');
        $journalsByRun = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->whereIn('payroll_run_id', $runIds)->whereNull('reversal_of_journal_id')->whereNull('reversed_by_journal_id')
            ->orderByDesc('id')->get(['id', 'payroll_run_id', 'status', 'total_debit_minor', 'total_credit_minor'])->unique('payroll_run_id')->keyBy('payroll_run_id');
        $branchTotalsByRun = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->join('payroll_employee_inputs as pi', function (JoinClause $join): void {
                $join->on('pi.employee_id', '=', 'i.employee_id')->on('pi.payroll_input_snapshot_id', '=', 'r.payroll_input_snapshot_id');
            })->where('i.company_id', $companyId)->whereIn('i.payroll_run_id', $runIds)->whereNotNull('pi.branch_id')
            ->selectRaw('i.payroll_run_id, COALESCE(SUM(i.gross_amount_minor + i.employer_contribution_amount_minor),0) total')->groupBy('i.payroll_run_id')->pluck('total', 'i.payroll_run_id');
        $rows = [];
        $totals = ['gross' => 0, 'deductions' => 0, 'employer' => 0, 'bonus' => 0, 'net' => 0, 'expected' => 0, 'journal_debit' => 0, 'journal_credit' => 0, 'branch_finance' => 0, 'difference' => 0];
        foreach ($runs as $run) {
            $runId = $this->integer($run, 'id');
            $items = $itemsByRun->get($runId);
            $bonus = $this->numeric($bonusesByRun->get($runId));
            $journal = $journalsByRun->get($runId);
            $gross = $this->integer($items, 'gross');
            $employer = $this->integer($items, 'employer');
            $expected = $gross + $employer;
            // This is intentionally the exact same immutable Branch snapshot source
            // used by BranchFinancialPerformanceService.
            $branchFinance = $this->numeric($branchTotalsByRun->get($runId));
            $debit = $this->integer($journal, 'total_debit_minor');
            $credit = $this->integer($journal, 'total_credit_minor');
            $accountingDue = in_array($this->text($run, 'status'), ['finalized', 'locked', 'paid'], true);
            $difference = abs($expected - $branchFinance);
            if ($accountingDue) {
                $difference += abs($debit - $credit) + ($journal === null ? $expected : abs($expected - $debit));
            }
            $row = ['run_id' => $runId, 'period_id' => $this->integer($run, 'period_id'), 'period' => $this->text($run, 'period_code'), 'run' => $this->text($run, 'run_code'), 'status' => $this->text($run, 'status'), 'accounting_due' => $accountingDue ? 1 : 0, 'gross' => $gross, 'deductions' => $this->integer($items, 'deductions'), 'employer' => $employer, 'bonus' => $bonus, 'net' => $this->integer($items, 'net'), 'expected' => $expected, 'journal_id' => $this->integer($journal, 'id'), 'journal_status' => $accountingDue ? $this->text($journal, 'status') : 'NOT_DUE', 'journal_debit' => $debit, 'journal_credit' => $credit, 'branch_finance' => $branchFinance, 'difference' => $difference, 'insurance_employee' => $this->integer($items, 'insurance_employee'), 'insurance_employer' => $this->integer($items, 'insurance_employer'), 'pit' => $this->integer($items, 'pit')];
            $rows[] = $row;
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key];
            }
        }

        return ['rows' => $rows, 'totals' => $totals, 'unreconciled' => count(array_filter($rows, static fn (array $row): bool => $row['difference'] !== 0))];
    }

    private function integer(?object $row, string $key): int
    {
        $value = $row?->{$key};

        return is_numeric($value) ? (int) $value : 0;
    }

    private function numeric(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(?object $row, string $key): string
    {
        $value = $row?->{$key};

        return is_string($value) ? $value : '';
    }
}
