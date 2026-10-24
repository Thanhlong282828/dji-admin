<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

final readonly class TreasuryCashPositionReadService
{
    private const POSTED = ['internally_posted', 'exported'];

    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, string $asOfDate, ?int $branchId = null): array
    {
        if (! Schema::hasTable('treasury_opening_balances')) {
            return ['schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'as_of_date' => $asOfDate, 'accounts' => [], 'available_cash' => []];
        }
        $asOf = CarbonImmutable::parse($asOfDate)->endOfDay();
        $accounts = $this->db->table('treasury_financial_accounts as financial')
            ->join('accounting_accounts as ledger', 'ledger.id', '=', 'financial.accounting_account_id')
            ->where(['financial.company_id' => $companyId, 'financial.status' => 'active'])
            ->when($branchId !== null, fn ($query) => $query->where('financial.branch_id', $branchId))
            ->orderBy('financial.code')
            ->get(['financial.id', 'financial.branch_id', 'financial.code', 'financial.name', 'financial.type', 'financial.currency', 'financial.accounting_account_id', 'ledger.code as ledger_code']);
        $openings = $this->db->table('treasury_opening_balances')->where(['company_id' => $companyId, 'status' => 'posted'])->whereDate('effective_date', '<=', $asOfDate)->orderByDesc('effective_date')->orderByDesc('id')->get()->groupBy('treasury_financial_account_id')->map(fn ($group) => $group->first());
        $movements = $this->db->table('treasury_transactions')->where('company_id', $companyId)->whereIn('status', ['recorded', 'reconciled'])->where('occurred_at', '<=', $asOf->toDateTimeString())->get(['financial_account_id', 'currency', 'direction', 'amount_minor', 'occurred_at'])->groupBy('financial_account_id');
        $mappingCounts = $this->db->table('treasury_financial_accounts')->where(['company_id' => $companyId, 'status' => 'active'])->selectRaw('accounting_account_id, COUNT(*) aggregate')->groupBy('accounting_account_id')->pluck('aggregate', 'accounting_account_id');
        $glRows = $this->db->table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')->where('line.company_id', $companyId)->whereIn('journal.status', self::POSTED)->whereDate('journal.posting_date', '<=', $asOfDate)->selectRaw('line.account_id, journal.branch_id, line.currency, SUM(CAST(line.debit_minor AS SIGNED)-CAST(line.credit_minor AS SIGNED)) balance')->groupBy('line.account_id', 'journal.branch_id', 'line.currency')->get();
        $rows = [];
        foreach ($accounts as $account) {
            $accountId = $this->integer($account->id ?? null);
            $opening = $openings->get($accountId);
            if ($opening === null) {
                $rows[] = $this->row($account, 'NOT_ENOUGH_EVIDENCE', null, null, null, null);

                continue;
            }
            $openingDate = $this->text($opening->effective_date ?? null);
            $movement = $movements->get($accountId, collect())->filter(fn (object $row): bool => $this->text($row->currency ?? null) === $this->text($account->currency ?? null) && $this->text($row->occurred_at ?? null) >= $openingDate.' 00:00:00')->sum(fn (object $row): int => $this->text($row->direction ?? null) === 'inflow' ? $this->integer($row->amount_minor ?? null) : -$this->integer($row->amount_minor ?? null));
            $balance = $this->integer($opening->opening_amount_minor ?? null) + $movement;
            $mappingCount = $this->integer($mappingCounts->get($this->integer($account->accounting_account_id ?? null)));
            $accountBranch = $branchId ?? $this->nullableInteger($account->branch_id ?? null);
            $gl = $glRows->filter(fn (object $row): bool => $this->integer($row->account_id ?? null) === $this->integer($account->accounting_account_id ?? null) && $this->text($row->currency ?? null) === $this->text($account->currency ?? null) && ($accountBranch === null || $this->nullableInteger($row->branch_id ?? null) === $accountBranch))->sum(fn (object $row): int => $this->integer($row->balance ?? null));
            $status = $mappingCount === 1 ? ($balance === $gl ? 'SUPPORTED' : 'RECONCILIATION_MISMATCH') : 'GL_MAPPING_AMBIGUOUS';
            $rows[] = $this->row($account, $status, $this->integer($opening->opening_amount_minor ?? null), $movement, $balance, $gl, $openingDate);
        }

        $available = [];
        foreach (array_values(array_unique(array_map(fn (array $row): string => $this->text($row['currency'] ?? null), array_filter($rows, fn (array $row): bool => in_array($row['type'], ['cash', 'bank'], true))))) as $currency) {
            $eligible = array_values(array_filter($rows, fn (array $row): bool => in_array($row['type'], ['cash', 'bank'], true) && $row['currency'] === $currency));
            $status = in_array('RECONCILIATION_MISMATCH', array_column($eligible, 'status'), true) ? 'RECONCILIATION_MISMATCH' : (in_array('GL_MAPPING_AMBIGUOUS', array_column($eligible, 'status'), true) ? 'RECONCILIATION_MISMATCH' : (in_array('NOT_ENOUGH_EVIDENCE', array_column($eligible, 'status'), true) ? 'NOT_ENOUGH_EVIDENCE' : 'SUPPORTED'));
            $available[$currency] = ['status' => $status, 'available_cash_minor' => $status === 'SUPPORTED' ? array_sum(array_column($eligible, 'balance_minor')) : null, 'account_count' => count($eligible)];
        }

        return ['schema_status' => 'READY', 'as_of_date' => $asOfDate, 'branch_id' => $branchId, 'accounts' => $rows, 'available_cash' => $available];
    }

    /** @return array<string,mixed> */
    public function workspace(int $companyId, string $asOfDate): array
    {
        if (! Schema::hasTable('treasury_opening_balances')) {
            return ['schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'openings' => [], 'cash_position' => $this->read($companyId, $asOfDate)];
        }
        $openings = $this->db->table('treasury_opening_balances as opening')->join('treasury_financial_accounts as account', 'account.id', '=', 'opening.treasury_financial_account_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'opening.accounting_journal_id')->where('opening.company_id', $companyId)->orderByDesc('opening.effective_date')->orderByDesc('opening.id')->get(['opening.*', 'account.code as account_code', 'account.name as account_name', 'account.type as account_type', 'journal.journal_number']);

        return ['schema_status' => 'READY', 'openings' => $openings, 'cash_position' => $this->read($companyId, $asOfDate)];
    }

    /** @return array<string,mixed> */
    private function row(object $account, string $status, ?int $opening, ?int $movement, ?int $balance, ?int $gl, ?string $openingDate = null): array
    {
        return ['account_id' => $this->integer($account->id ?? null), 'branch_id' => $this->nullableInteger($account->branch_id ?? null), 'code' => $this->text($account->code ?? null), 'name' => $this->text($account->name ?? null), 'type' => $this->text($account->type ?? null), 'currency' => $this->text($account->currency ?? null), 'ledger_code' => $this->text($account->ledger_code ?? null), 'status' => $status, 'opening_effective_date' => $openingDate, 'opening_amount_minor' => $opening, 'movement_minor' => $movement, 'balance_minor' => $balance, 'gl_balance_minor' => $gl];
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : $this->integer($value);
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
