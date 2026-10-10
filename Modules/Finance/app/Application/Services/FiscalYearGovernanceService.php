<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

final readonly class FiscalYearGovernanceService
{
    public function __construct(
        private ConnectionInterface $db,
        private SourceAccountingPostingService $posting,
        private AccountingPeriodCloseService $periodClose,
    ) {}

    /** @param array{name:string,start_date:string,end_date:string} $data */
    public function createFiscalYear(int $companyId, User $actor, array $data): int
    {
        if ($data['start_date'] > $data['end_date']) {
            throw ValidationException::withMessages(['end_date' => 'FISCAL_YEAR_DATE_RANGE_INVALID']);
        }

        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $overlap = $this->db->table('accounting_fiscal_years')->where('company_id', $companyId)->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'FISCAL_YEAR_DATE_RANGE_OVERLAP']);
            }

            return (int) $this->db->table('accounting_fiscal_years')->insertGetId(['company_id' => $companyId, 'name' => trim($data['name']), 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'status' => 'draft', 'created_by' => $actor->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function openFiscalYear(int $companyId, int $fiscalYearId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $fiscalYearId, $actor): int {
            $year = $this->fiscalYear($companyId, $fiscalYearId);
            if ($this->text($year, 'status') !== 'draft') {
                throw ValidationException::withMessages(['fiscal_year' => 'FISCAL_YEAR_NOT_DRAFT']);
            }
            $this->db->table('accounting_fiscal_years')->where('id', $fiscalYearId)->update(['status' => 'open', 'opened_by' => $actor->id, 'opened_at' => now(), 'version' => $this->integer($year, 'version') + 1, 'updated_at' => now()]);

            return $fiscalYearId;
        }, 3);
    }

    /** @param array{year:int,month:int,start_date:string,end_date:string} $data */
    public function createPeriod(int $companyId, int $fiscalYearId, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $fiscalYearId, $data): int {
            $year = $this->fiscalYear($companyId, $fiscalYearId);
            if ($this->text($year, 'status') !== 'open') {
                throw ValidationException::withMessages(['fiscal_year' => 'FISCAL_YEAR_NOT_OPEN']);
            }
            if ($data['start_date'] > $data['end_date'] || $data['start_date'] < $this->text($year, 'start_date') || $data['end_date'] > $this->text($year, 'end_date')) {
                throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_OUTSIDE_FISCAL_YEAR']);
            }
            $overlap = $this->db->table('accounting_periods')->where('company_id', $companyId)->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_DATE_RANGE_OVERLAP']);
            }

            return (int) $this->db->table('accounting_periods')->insertGetId(['company_id' => $companyId, 'fiscal_year_id' => $fiscalYearId, 'year' => $data['year'], 'month' => $data['month'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function closePeriod(int $companyId, int $periodId, User $actor, string $reason): int
    {
        $yearId = $this->db->table('accounting_periods')->where(['id' => $periodId, 'company_id' => $companyId])->value('fiscal_year_id');
        if (! is_numeric($yearId)) {
            throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_WITHOUT_FISCAL_YEAR']);
        }

        return $this->periodClose->request($companyId, $periodId, 'close', $actor, $reason);
    }

    public function closeFiscalYear(int $companyId, int $fiscalYearId, int $retainedEarningsAccountId, string $idempotencyKey, User $actor, ?string $reason): int
    {
        return $this->db->transaction(function () use ($companyId, $fiscalYearId, $retainedEarningsAccountId, $idempotencyKey, $actor, $reason): int {
            $existing = $this->db->table('accounting_fiscal_year_close_requests')->where(['company_id' => $companyId, 'idempotency_key' => $idempotencyKey])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $year = $this->fiscalYear($companyId, $fiscalYearId);
            if ($this->text($year, 'status') !== 'open') {
                throw ValidationException::withMessages(['fiscal_year' => 'FISCAL_YEAR_NOT_OPEN']);
            }
            if ($this->db->table('accounting_periods')->where(['company_id' => $companyId, 'fiscal_year_id' => $fiscalYearId])->where('status', '<>', 'closed')->exists()) {
                throw ValidationException::withMessages(['periods' => 'FISCAL_YEAR_HAS_OPEN_PERIODS']);
            }
            $account = $this->db->table('accounting_accounts')->where(['id' => $retainedEarningsAccountId, 'company_id' => $companyId, 'account_type' => 'equity', 'status' => 'active'])->firstOrFail();
            $profitAndLossRows = $this->profitAndLossAccounts($companyId, $this->text($year, 'start_date'), $this->text($year, 'end_date'));
            $currency = $this->currency($profitAndLossRows);
            $summary = $this->profitAndLoss($companyId, $this->text($year, 'start_date'), $this->text($year, 'end_date'));

            return (int) $this->db->table('accounting_fiscal_year_close_requests')->insertGetId(['company_id' => $companyId, 'fiscal_year_id' => $fiscalYearId, 'status' => 'submitted', 'currency' => $currency, 'revenue_minor' => $summary['revenue'], 'expense_minor' => $summary['expense'], 'net_income_minor' => $summary['revenue'] - $summary['expense'], 'retained_earnings_account_id' => $this->integer($account, 'id'), 'idempotency_key' => $idempotencyKey, 'reason' => $reason, 'requested_by' => $actor->id, 'requested_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function carryForward(int $companyId, int $closeRequestId, int $targetFiscalYearId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $closeRequestId, $targetFiscalYearId, $actor): int {
            $request = $this->db->table('accounting_fiscal_year_close_requests')->where(['id' => $closeRequestId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $target = $this->fiscalYear($companyId, $targetFiscalYearId);
            if ($this->text($target, 'status') !== 'open') {
                throw ValidationException::withMessages(['target_fiscal_year' => 'TARGET_FISCAL_YEAR_NOT_OPEN']);
            }
            $source = $this->fiscalYear($companyId, $this->integer($request, 'fiscal_year_id'));
            if ($this->integer($request, 'requested_by') === $actor->id) {
                throw ValidationException::withMessages(['actor' => 'FISCAL_YEAR_MAKER_CHECKER_REQUIRED']);
            }
            if ($this->text($source, 'status') === 'open' && $this->text($request, 'status') === 'submitted') {
                $closingRows = $this->profitAndLossAccounts($companyId, $this->text($source, 'start_date'), $this->text($source, 'end_date'));
                $closingJournal = $this->posting->postFiscalYearClose($companyId, $closeRequestId, $this->text($source, 'end_date'), $this->currency($closingRows), $this->closingLines($closingRows, $request), $actor->id);
                $this->db->table('accounting_fiscal_years')->where('id', $this->integer($source, 'id'))->update(['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now(), 'version' => $this->integer($source, 'version') + 1, 'updated_at' => now()]);
                $this->db->table('accounting_fiscal_year_close_requests')->where('id', $closeRequestId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'closing_journal_id' => $closingJournal, 'version' => $this->integer($request, 'version') + 1, 'updated_at' => now()]);
                $request = $this->db->table('accounting_fiscal_year_close_requests')->where('id', $closeRequestId)->lockForUpdate()->firstOrFail();
                $source = $this->fiscalYear($companyId, $this->integer($request, 'fiscal_year_id'));
            }
            if ($this->text($source, 'status') !== 'closed') {
                throw ValidationException::withMessages(['source_fiscal_year' => 'SOURCE_FISCAL_YEAR_NOT_CLOSED']);
            }
            $existing = $this->nullableInteger($request, 'opening_journal_id');
            if ($existing !== null) {
                return $existing;
            }

            $balances = $this->balanceSheetAccounts($companyId, $this->text($source, 'end_date'));
            $journal = $this->posting->post($companyId, null, 'fiscal_year_carry_forward', $closeRequestId, 'opening_balance', $this->text($target, 'start_date'), $this->currency($balances), $this->openingLines($balances), $actor->id);
            foreach ($balances as $row) {
                $this->db->table('accounting_opening_balance_lines')->insert(['company_id' => $companyId, 'fiscal_year_close_request_id' => $closeRequestId, 'source_fiscal_year_id' => $this->integer($source, 'id'), 'target_fiscal_year_id' => $targetFiscalYearId, 'account_id' => $this->integer($row, 'account_id'), 'currency' => $this->text($row, 'currency'), 'debit_minor' => max(0, $this->integer($row, 'balance')), 'credit_minor' => max(0, -$this->integer($row, 'balance')), 'source_journal_id' => $this->integer($request, 'closing_journal_id'), 'opening_journal_id' => $journal, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->db->table('accounting_fiscal_year_close_requests')->where('id', $closeRequestId)->update(['opening_journal_id' => $journal, 'posted_by' => $actor->id, 'posted_at' => now(), 'status' => 'carried_forward', 'version' => $this->integer($request, 'version') + 1, 'updated_at' => now()]);

            return $journal;
        }, 3);
    }

    /** @return array{revenue:int,expense:int} */
    private function profitAndLoss(int $companyId, string $from, string $to): array
    {
        $rows = $this->postedLines($companyId, $to)->whereBetween('j.posting_date', [$from, $to])->whereIn('a.account_type', ['revenue', 'income', 'expense'])->select('a.account_type')->selectRaw('SUM(l.debit_minor) debit, SUM(l.credit_minor) credit')->groupBy('a.account_type')->get();
        $result = ['revenue' => 0, 'expense' => 0];
        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $type = $this->text($values, 'account_type');
            $amount = in_array($type, ['revenue', 'income'], true) ? $this->integer($values, 'credit') - $this->integer($values, 'debit') : $this->integer($values, 'debit') - $this->integer($values, 'credit');
            $result[in_array($type, ['revenue', 'income'], true) ? 'revenue' : 'expense'] += $amount;
        }

        return $result;
    }

    /** @return array<int,\stdClass> */
    private function balanceSheetAccounts(int $companyId, string $to): array
    {
        return $this->postedLines($companyId, $to)->whereIn('a.account_type', ['asset', 'contra_asset', 'liability', 'equity'])->select('a.id as account_id', 'a.code', 'a.account_type', 'l.currency')->selectRaw('SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)) balance')->groupBy('a.id', 'a.code', 'a.account_type', 'l.currency')->havingRaw('SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)) <> 0')->get()->all();
    }

    /** @return array<int,\stdClass> */
    private function profitAndLossAccounts(int $companyId, string $from, string $to): array
    {
        return $this->postedLines($companyId, $to)->whereBetween('j.posting_date', [$from, $to])->whereIn('a.account_type', ['revenue', 'income', 'expense'])->select('a.id as account_id', 'a.code', 'a.account_type', 'l.currency')->selectRaw('SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)) balance')->groupBy('a.id', 'a.code', 'a.account_type', 'l.currency')->havingRaw('SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)) <> 0')->get()->all();
    }

    private function postedLines(int $companyId, string $to): Builder
    {
        return $this->db->table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->where('j.company_id', $companyId)->where('l.company_id', $companyId)->where('a.company_id', $companyId)->whereIn('j.status', ['internally_posted', 'exported'])->whereDate('j.posting_date', '<=', $to);
    }

    /**
     * @param  array<int,\stdClass>  $balances
     * @return list<array{account_code:string,debit:int,credit:int,description:string}>
     */
    private function openingLines(array $balances): array
    {
        return array_values(array_map(function (object $row): array {
            $values = get_object_vars($row);
            $balance = $this->integer($values, 'balance');

            return ['account_code' => $this->text($values, 'code'), 'debit' => max(0, $balance), 'credit' => max(0, -$balance), 'description' => 'Fiscal year opening balance carry forward'];
        }, $balances));
    }

    /**
     * @param  array<int,\stdClass>  $balances
     * @return list<array{account_code:string,debit:int,credit:int,description:string}>
     */
    private function closingLines(array $balances, object $request): array
    {
        $lines = [];
        $netDebit = 0;
        $netCredit = 0;
        foreach ($balances as $row) {
            $values = get_object_vars($row);
            $balance = $this->integer($values, 'balance');
            $debit = max(0, -$balance);
            $credit = max(0, $balance);
            $netDebit += $debit;
            $netCredit += $credit;
            $lines[] = ['account_code' => $this->text($values, 'code'), 'debit' => $debit, 'credit' => $credit, 'description' => 'Fiscal year profit and loss closing'];
        }
        $retained = $this->db->table('accounting_accounts')->where(['id' => $this->integer($request, 'retained_earnings_account_id'), 'company_id' => $this->integer($request, 'company_id')])->firstOrFail();
        $lines[] = ['account_code' => $this->text($retained, 'code'), 'debit' => max(0, $netCredit - $netDebit), 'credit' => max(0, $netDebit - $netCredit), 'description' => 'Net income transferred to retained earnings'];

        return $lines;
    }

    /** @param array<int,\stdClass> $balances */
    private function currency(array $balances): string
    {
        $currencies = array_unique(array_map(fn (object $row): string => $this->text(get_object_vars($row), 'currency'), $balances));
        if (count($currencies) !== 1) {
            throw ValidationException::withMessages(['currency' => 'CARRY_FORWARD_REQUIRES_SINGLE_CURRENCY']);
        }

        return $currencies[0];
    }

    private function fiscalYear(int $companyId, int $id): object
    {
        return $this->db->table('accounting_fiscal_years')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    /** @param array<mixed>|object $row */
    private function integer(array|object $row, string $key): int
    {
        $value = is_array($row) ? ($row[$key] ?? null) : (get_object_vars($row)[$key] ?? null);

        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid fiscal year integer.'));
    }

    /** @param array<mixed>|object $row */
    private function nullableInteger(array|object $row, string $key): ?int
    {
        $value = is_array($row) ? ($row[$key] ?? null) : (get_object_vars($row)[$key] ?? null);

        return $value === null ? null : (is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid optional fiscal year integer.'));
    }

    /** @param array<mixed>|object $row */
    private function text(array|object $row, string $key): string
    {
        $value = is_array($row) ? ($row[$key] ?? null) : (get_object_vars($row)[$key] ?? null);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid fiscal year text.');
    }
}
