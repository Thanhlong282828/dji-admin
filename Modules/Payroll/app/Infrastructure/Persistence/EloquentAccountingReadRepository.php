<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\AccountingViewModel;
use Modules\Payroll\Domain\Repositories\AccountingReadRepositoryInterface;

final readonly class EloquentAccountingReadRepository implements AccountingReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function accounts(int $companyId, array $filters): AccountingViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('accounting_accounts as a')->leftJoin('accounting_accounts as p', 'p.id', '=', 'a.parent_id')->where('a.company_id', $companyId)
            ->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $s): Builder => $s->where('a.code', 'like', '%'.$filters['search'].'%')->orWhere('a.name', 'like', '%'.$filters['search'].'%')))
            ->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('a.status', $filters['status']))
            ->select(['a.*', 'p.code as parent_code'])->orderBy('a.code')->offset(($page - 1) * 25)->limit(26)->get();

        return new AccountingViewModel($rows->take(25)->map(fn (object $r): array => $this->accountRow($r))->values()->all(), page: $page, hasMore: $rows->count() > 25);
    }

    public function account(int $companyId, ?int $accountId): AccountingViewModel
    {
        if ($accountId === null) {
            return new AccountingViewModel(options: $this->options($companyId));
        }
        $row = $this->db->table('accounting_accounts as a')->leftJoin('accounting_accounts as p', 'p.id', '=', 'a.parent_id')->where('a.company_id', $companyId)->where('a.id', $accountId)->select(['a.*', 'p.code as parent_code'])->first();
        abort_unless(is_object($row), 404);

        return new AccountingViewModel(detail: $this->accountRow($row), options: $this->options($companyId));
    }

    public function policies(int $companyId, array $filters): AccountingViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('payroll_accounting_policies as p')->where('p.company_id', $companyId)
            ->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $s): Builder => $s->where('p.code', 'like', '%'.$filters['search'].'%')->orWhere('p.name', 'like', '%'.$filters['search'].'%')))
            ->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('p.status', $filters['status']))
            ->orderByDesc('p.effective_from')->orderByDesc('p.version')->orderByDesc('p.id')->offset(($page - 1) * 25)->limit(26)->get();

        return new AccountingViewModel($rows->take(25)->map(fn (object $r): array => $this->policyRow($r))->values()->all(), page: $page, hasMore: $rows->count() > 25);
    }

    public function policy(int $companyId, ?int $policyId): AccountingViewModel
    {
        if ($policyId === null) {
            return new AccountingViewModel(options: $this->options($companyId));
        }
        $row = $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('id', $policyId)->first();
        abort_unless(is_object($row), 404);

        return new AccountingViewModel(detail: $this->policyRow($row), options: $this->options($companyId));
    }

    public function mappings(int $companyId, array $filters): AccountingViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('payroll_accounting_mappings as m')->join('payroll_accounting_policies as p', 'p.id', '=', 'm.accounting_policy_id')->leftJoin('accounting_accounts as d', 'd.id', '=', 'm.debit_account_id')->leftJoin('accounting_accounts as c', 'c.id', '=', 'm.credit_account_id')->where('m.company_id', $companyId)
            ->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $s): Builder => $s->where('m.source_code', 'like', '%'.$filters['search'].'%')->orWhere('m.source_type', 'like', '%'.$filters['search'].'%')))
            ->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('m.status', $filters['status']))
            ->select(['m.*', 'p.code as policy_code', 'p.version as policy_version', 'd.code as debit_code', 'c.code as credit_code'])->orderByDesc('m.priority')->orderByDesc('m.id')->offset(($page - 1) * 25)->limit(26)->get();

        return new AccountingViewModel($rows->take(25)->map(fn (object $r): array => $this->mappingRow($r))->values()->all(), options: $this->options($companyId), page: $page, hasMore: $rows->count() > 25);
    }

    public function mapping(int $companyId, ?int $mappingId): AccountingViewModel
    {
        if ($mappingId === null) {
            return new AccountingViewModel(options: $this->options($companyId));
        }
        $row = $this->db->table('payroll_accounting_mappings as m')->join('payroll_accounting_policies as p', 'p.id', '=', 'm.accounting_policy_id')->leftJoin('accounting_accounts as d', 'd.id', '=', 'm.debit_account_id')->leftJoin('accounting_accounts as c', 'c.id', '=', 'm.credit_account_id')->where('m.company_id', $companyId)->where('m.id', $mappingId)->select(['m.*', 'p.code as policy_code', 'p.version as policy_version', 'd.code as debit_code', 'c.code as credit_code'])->first();
        abort_unless(is_object($row), 404);

        return new AccountingViewModel(detail: $this->mappingRow($row), options: $this->options($companyId));
    }

    public function journals(int $companyId, array $filters): AccountingViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('payroll_accounting_journals as j')->join('payroll_runs as r', 'r.id', '=', 'j.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'j.payroll_period_id')->where('j.company_id', $companyId)
            ->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $s): Builder => $s->where('j.journal_number', 'like', '%'.$filters['search'].'%')->orWhere('r.run_number', 'like', '%'.$filters['search'].'%')))
            ->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('j.status', $filters['status']))
            ->select(['j.*', 'r.run_number', 'p.code as period_code'])->orderByDesc('j.created_at')->orderByDesc('j.id')->offset(($page - 1) * 25)->limit(26)->get();

        return new AccountingViewModel($rows->take(25)->map(fn (object $r): array => $this->journalRow($r))->values()->all(), page: $page, hasMore: $rows->count() > 25);
    }

    public function journal(int $companyId, int $journalId): AccountingViewModel
    {
        $row = $this->db->table('payroll_accounting_journals as j')->join('payroll_runs as r', 'r.id', '=', 'j.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'j.payroll_period_id')->join('payroll_accounting_policies as ap', 'ap.id', '=', 'j.accounting_policy_id')->where('j.company_id', $companyId)->where('j.id', $journalId)->select(['j.*', 'r.run_number', 'p.code as period_code', 'ap.code as policy_code', 'ap.name as policy_name'])->first();
        abort_unless(is_object($row), 404);
        $lines = $this->db->table('payroll_accounting_journal_lines')->where('company_id', $companyId)->where('journal_id', $journalId)->orderBy('line_number')->get()->map(fn (object $r): array => ['line_number' => $this->i($r, 'line_number'), 'account_code' => $this->s($r, 'account_code_snapshot'), 'account_name' => $this->s($r, 'account_name_snapshot'), 'description' => $this->s($r, 'description'), 'debit_minor' => $this->i($r, 'debit_minor'), 'credit_minor' => $this->i($r, 'credit_minor'), 'currency' => $this->s($r, 'currency'), 'cost_center' => $this->n($r, 'cost_center_code_snapshot'), 'organization_unit' => $this->n($r, 'organization_unit_code_snapshot'), 'source_code' => $this->s($r, 'source_code'), 'source_count' => $this->i($r, 'source_count')])->all();
        $issues = $this->db->table('payroll_accounting_validation_issues')->where('company_id', $companyId)->where('journal_id', $journalId)->orderBy('id')->get()->map(fn (object $r): array => ['code' => $this->s($r, 'code'), 'severity' => $this->s($r, 'severity'), 'message' => $this->s($r, 'message'), 'blocking' => $this->i($r, 'is_blocking') === 1])->all();
        $history = $this->db->table('activity_log')->where('log_name', 'payroll-accounting')->where('properties->company_id', $companyId)->where('properties->journal_id', $journalId)->orderByDesc('id')->limit(100)->get(['event', 'description', 'created_at'])->map(fn (object $r): array => ['event' => $this->s($r, 'event'), 'description' => $this->s($r, 'description'), 'created_at' => $this->s($r, 'created_at')])->all();
        $detail = $this->journalRow($row) + ['policy_code' => $this->s($row, 'policy_code'), 'policy_name' => $this->s($row, 'policy_name'), 'lines' => $lines, 'issues' => $issues, 'history' => $history, 'file_name' => $this->n($row, 'file_name'), 'checksum' => $this->n($row, 'checksum'), 'rejection_reason' => $this->n($row, 'rejection_reason'), 'reversal_of_journal_id' => $this->ni($row, 'reversal_of_journal_id'), 'reversed_by_journal_id' => $this->ni($row, 'reversed_by_journal_id')];

        return new AccountingViewModel(detail: $detail);
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function options(int $companyId): array
    {
        $map = fn (object $r): array => $this->objectValues($r);

        return [
            'accounts' => $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name'])->map($map)->all(),
            'policies' => $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->orderByDesc('version')->get(['id', 'code', 'name', 'version'])->map($map)->all(),
            'cost_centers' => $this->db->table('cost_centers')->where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name'])->map($map)->all(),
            'organization_units' => $this->db->table('organization_units')->where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name'])->map($map)->all(),
            'runs' => $this->db->table('payroll_runs as r')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->where('r.company_id', $companyId)->where('r.status', 'finalized')->whereNotExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.payroll_run_id', 'r.id')->whereNotNull('j.active_intent_key'))->orderByDesc('r.id')->get(['r.id', 'r.run_number', 'r.calculation_version', 'r.currency_code', 'p.code as period_code'])->map($map)->all(),
        ];
    }

    /** @return array<string,int|string|null> */
    private function policyRow(object $r): array
    {
        return ['id' => $this->i($r, 'id'), 'code' => $this->s($r, 'code'), 'name' => $this->s($r, 'name'), 'version' => $this->i($r, 'version'), 'currency' => $this->s($r, 'currency'), 'aggregation_strategy' => $this->s($r, 'aggregation_strategy'), 'rounding_rule' => $this->s($r, 'rounding_rule'), 'effective_from' => $this->s($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to'), 'status' => $this->s($r, 'status'), 'note' => $this->n($r, 'note')];
    }

    /** @return array<string,int|string|bool|null> */
    private function accountRow(object $r): array
    {
        return ['id' => $this->i($r, 'id'), 'code' => $this->s($r, 'code'), 'name' => $this->s($r, 'name'), 'account_type' => $this->s($r, 'account_type'), 'normal_balance' => $this->s($r, 'normal_balance'), 'currency' => $this->n($r, 'currency'), 'parent_id' => $this->ni($r, 'parent_id'), 'parent_code' => $this->n($r, 'parent_code'), 'allow_posting' => $this->i($r, 'allow_posting') === 1, 'status' => $this->s($r, 'status'), 'effective_from' => $this->s($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to')];
    }

    /** @return array<string,int|string|null> */
    private function mappingRow(object $r): array
    {
        return ['id' => $this->i($r, 'id'), 'accounting_policy_id' => $this->i($r, 'accounting_policy_id'), 'policy_code' => $this->s($r, 'policy_code'), 'policy_version' => $this->i($r, 'policy_version'), 'source_type' => $this->s($r, 'source_type'), 'source_code' => $this->s($r, 'source_code'), 'debit_account_id' => $this->ni($r, 'debit_account_id'), 'credit_account_id' => $this->ni($r, 'credit_account_id'), 'debit_code' => $this->n($r, 'debit_code'), 'credit_code' => $this->n($r, 'credit_code'), 'cost_center_id' => $this->ni($r, 'cost_center_id'), 'organization_unit_id' => $this->ni($r, 'organization_unit_id'), 'priority' => $this->i($r, 'priority'), 'effective_from' => $this->s($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to'), 'status' => $this->s($r, 'status')];
    }

    /** @return array<string,int|string|null> */
    private function journalRow(object $r): array
    {
        return ['id' => $this->i($r, 'id'), 'uuid' => $this->s($r, 'uuid'), 'policy_version' => $this->i($r, 'policy_version'), 'journal_number' => $this->s($r, 'journal_number'), 'run_number' => $this->s($r, 'run_number'), 'period_code' => $this->s($r, 'period_code'), 'status' => $this->s($r, 'status'), 'posting_date' => $this->s($r, 'posting_date'), 'resolution_date' => $this->s($r, 'resolution_date'), 'currency' => $this->s($r, 'currency'), 'calculation_version' => $this->i($r, 'calculation_version'), 'line_count' => $this->i($r, 'line_count'), 'total_debit_minor' => $this->i($r, 'total_debit_minor'), 'total_credit_minor' => $this->i($r, 'total_credit_minor'), 'created_by' => $this->i($r, 'created_by'), 'created_at' => $this->s($r, 'created_at'), 'reviewed_at' => $this->n($r, 'reviewed_at'), 'approved_at' => $this->n($r, 'approved_at'), 'posted_at' => $this->n($r, 'posted_at'), 'exported_at' => $this->n($r, 'exported_at')];
    }

    private function i(object $r, string $k): int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function ni(object $r, string $k): ?int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    private function s(object $r, string $k): string
    {
        return $this->n($r, $k) ?? '';
    }

    private function n(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }

    /** @return array<string,mixed> */
    private function objectValues(object $row): array
    {
        $values = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
