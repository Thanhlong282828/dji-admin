<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

final readonly class FinancialStatementReadService
{
    private const POSTED = ['internally_posted', 'exported'];

    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function report(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $accountId, int $page, ?int $branchId = null, ?string $currency = null, ?User $actor = null): array
    {
        $lines = $this->postedLines($companyId, $dateFrom, $dateTo, $branchId, $currency);
        $trial = (clone $lines)->select('a.id as account_id', 'a.code', 'a.name', 'a.account_type', 'a.normal_balance', 'l.currency')
            ->selectRaw('SUM(l.debit_minor) total_debit_minor, SUM(l.credit_minor) total_credit_minor')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type', 'a.normal_balance', 'l.currency')->orderBy('a.code')->get()
            ->map(static function (object $row): array {
                $values = get_object_vars($row);
                $debit = self::integer($values['total_debit_minor'] ?? null);
                $credit = self::integer($values['total_credit_minor'] ?? null);
                $normalBalance = self::text($values['normal_balance'] ?? null);

                return ['account_id' => self::integer($values['account_id'] ?? null), 'code' => self::text($values['code'] ?? null), 'name' => self::text($values['name'] ?? null), 'account_type' => self::text($values['account_type'] ?? null), 'normal_balance' => $normalBalance, 'currency' => self::text($values['currency'] ?? null), 'debit' => $debit, 'credit' => $credit, 'balance' => $normalBalance === 'credit' ? $credit - $debit : $debit - $credit];
            })->all();

        $totals = ['debit' => 0, 'credit' => 0];
        $income = ['revenue' => 0, 'cogs' => 0, 'gross_profit' => 0, 'payroll' => 0, 'operating_expense' => 0, 'depreciation' => 0, 'other_expense' => 0, 'expense' => 0, 'profit' => 0];
        $balance = ['cash_clearing' => 0, 'accounts_receivable' => 0, 'inventory' => 0, 'fixed_assets' => 0, 'accumulated_depreciation' => 0, 'assets' => 0, 'accounts_payable' => 0, 'payroll_liabilities' => 0, 'other_liabilities' => 0, 'liabilities' => 0, 'equity' => 0, 'difference' => 0];
        $types = [];
        foreach ($trial as $row) {
            $totals['debit'] += $row['debit'];
            $totals['credit'] += $row['credit'];
            $types[$row['account_type']] = true;
            if (in_array($row['account_type'], ['revenue', 'income'], true)) {
                $amount = $row['credit'] - $row['debit'];
                $income['revenue'] += $amount;
            } elseif ($row['account_type'] === 'expense') {
                $amount = $row['debit'] - $row['credit'];
                $income['expense'] += $amount;
                match (true) {
                    $row['code'] === '5000' => $income['cogs'] += $amount,
                    str_starts_with($row['code'], '6421') => $income['payroll'] += $amount,
                    $row['code'] === '5100' => $income['operating_expense'] += $amount,
                    $row['code'] === '5200' => $income['depreciation'] += $amount,
                    default => $income['other_expense'] += $amount,
                };
            }
        }
        $income['gross_profit'] = $income['revenue'] - $income['cogs'];
        $income['profit'] = $income['revenue'] - $income['expense'];

        // Balance Sheet is a point-in-time statement, so it includes all posted
        // activity through date_to rather than only the selected period movement.
        $closingRows = $this->accountBalances($companyId, null, $dateTo, $branchId, $currency);
        foreach ($closingRows as $row) {
            $type = self::text($row['account_type']);
            $code = self::text($row['code']);
            $debit = self::integer($row['total_debit_minor']);
            $credit = self::integer($row['total_credit_minor']);
            if ($type === 'asset') {
                $amount = $debit - $credit;
                $balance['assets'] += $amount;
                match ($code) {
                    '1100' => $balance['cash_clearing'] += $amount,
                    '1150' => $balance['accounts_receivable'] += $amount,
                    '1200' => $balance['inventory'] += $amount,
                    '1300' => $balance['fixed_assets'] += $amount,
                    default => null,
                };
            } elseif ($type === 'contra_asset') {
                $amount = $credit - $debit;
                $balance['accumulated_depreciation'] += $amount;
                $balance['assets'] -= $amount;
            } elseif ($type === 'liability') {
                $amount = $credit - $debit;
                $balance['liabilities'] += $amount;
                match (true) {
                    in_array($code, ['2100', '2150'], true) => $balance['accounts_payable'] += $amount,
                    str_starts_with($code, '3341') => $balance['payroll_liabilities'] += $amount,
                    default => $balance['other_liabilities'] += $amount,
                };
            } elseif ($type === 'equity') {
                $balance['equity'] += $credit - $debit;
            }
        }
        $balance['difference'] = $balance['assets'] - $balance['liabilities'] - $balance['equity'];

        $ledger = $this->postedLines($companyId, $dateFrom, $dateTo, $branchId, $currency)
            ->when($accountId !== null, fn (Builder $query): Builder => $query->where('a.id', $accountId))
            ->select('j.id as journal_id', 'j.journal_number', 'j.posting_date', 'j.status', 'l.line_number', 'l.description', 'l.source_type', 'l.source_code', 'l.account_id', 'l.account_code_snapshot', 'l.account_name_snapshot', 'l.debit_minor', 'l.credit_minor', 'l.currency', 'l.cost_center_code_snapshot', 'l.organization_unit_code_snapshot')
            ->orderByDesc('j.posting_date')->orderByDesc('j.id')->orderBy('l.line_number')->paginate(50, ['*'], 'page', max(1, $page))->withQueryString();

        $journalSummary = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->whereIn('status', self::POSTED)
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($dateFrom !== null, fn (Builder $query): Builder => $query->whereDate('posting_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query): Builder => $query->whereDate('posting_date', '<=', $dateTo))
            ->when($currency !== null, fn (Builder $query): Builder => $query->where('currency', $currency))
            ->selectRaw('COUNT(*) journals, COALESCE(SUM(total_debit_minor),0) debit, COALESCE(SUM(total_credit_minor),0) credit')->first();

        $cashRows = $this->db->table('treasury_transactions')->where('company_id', $companyId)->where('status', 'recorded')->where('source_type', '<>', 'treasury_internal_transfer_leg')
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($dateFrom !== null, fn (Builder $query): Builder => $query->whereDate('occurred_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query): Builder => $query->whereDate('occurred_at', '<=', $dateTo))
            ->when($currency !== null, fn (Builder $query): Builder => $query->where('currency', $currency))
            ->selectRaw('source_type, direction, COALESCE(SUM(amount_minor),0) amount')->groupBy('source_type', 'direction')->get();
        $cashFlow = ['customer_collection_inflow' => 0, 'vendor_payment_outflow' => 0, 'expense_payment_outflow' => 0, 'operating_inflow' => 0, 'operating_outflow' => 0, 'investing_inflow' => 0, 'investing_outflow' => 0, 'financing_inflow' => 0, 'financing_outflow' => 0, 'unclassified_inflow' => 0, 'unclassified_outflow' => 0, 'unclassified' => 0, 'net_movement' => 0];
        foreach ($cashRows as $row) {
            $values = get_object_vars($row);
            $source = self::text($values['source_type'] ?? null);
            $direction = self::text($values['direction'] ?? null);
            $amount = self::integer($values['amount'] ?? null);
            $bucket = match (true) {
                in_array($source, ['customer_collection', 'customer_refund', 'ap_payment', 'expense_payment', 'finance_expense_payment'], true) => 'operating_'.$direction,
                in_array($source, ['asset_acquisition', 'asset_disposal'], true) => 'investing_'.$direction,
                in_array($source, ['loan_drawdown', 'loan_repayment', 'equity_contribution', 'dividend'], true) => 'financing_'.$direction,
                default => null,
            };
            if ($bucket !== null && array_key_exists($bucket, $cashFlow)) {
                $cashFlow[$bucket] += $amount;
            } else {
                $cashFlow['unclassified'] += $amount;
                $cashFlow['unclassified_'.$direction] += $amount;
            }
            match ($source) {
                'customer_collection' => $cashFlow['customer_collection_inflow'] += $direction === 'inflow' ? $amount : 0,
                'ap_payment' => $cashFlow['vendor_payment_outflow'] += $direction === 'outflow' ? $amount : 0,
                'expense_payment', 'finance_expense_payment' => $cashFlow['expense_payment_outflow'] += $direction === 'outflow' ? $amount : 0,
                default => null,
            };
            $cashFlow['net_movement'] += $direction === 'inflow' ? $amount : -$amount;
        }

        $openingBoundary = $dateFrom === null ? null : (new \DateTimeImmutable($dateFrom))->modify('-1 day')->format('Y-m-d');
        $openingEquity = $openingBoundary === null ? 0 : $this->equityBalance($companyId, null, $openingBoundary, $branchId, $currency);
        $closingEquity = $this->equityBalance($companyId, null, $dateTo, $branchId, $currency);
        $dividends = $this->postedLines($companyId, $dateFrom, $dateTo, $branchId, $currency)->whereIn('l.source_type', ['dividend', 'equity_dividend'])->selectRaw('COALESCE(SUM(l.debit_minor - l.credit_minor),0) amount')->value('amount');
        $dividendAmount = self::integer($dividends);
        $directEquityMovement = $closingEquity - $openingEquity - $income['profit'] + $dividendAmount;
        $equity = ['opening' => $openingEquity, 'profit' => $income['profit'], 'dividend' => $dividendAmount, 'direct_movement' => $directEquityMovement, 'closing' => $closingEquity, 'difference' => $closingEquity - ($openingEquity + $income['profit'] + $directEquityMovement - $dividendAmount)];

        $canViewAccounting = $actor?->can('payroll.accounting.view') ?? true;
        $sourceScope = array_filter(['company_id' => $companyId, 'branch_id' => $branchId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $currency], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'trial_balance' => $trial,
            'general_ledger' => $ledger,
            'totals' => ['journals' => self::integer($journalSummary === null ? null : (get_object_vars($journalSummary)['journals'] ?? null)), 'debit' => $totals['debit'], 'credit' => $totals['credit'], 'difference' => $totals['debit'] - $totals['credit']],
            'income_statement' => $income,
            'balance_sheet' => $balance,
            'cash_flow' => $cashFlow,
            'equity_statement' => $equity,
            'coverage' => ['account_types' => array_keys($types), 'income_statement_complete' => isset($types['expense']) && (isset($types['revenue']) || isset($types['income'])), 'balance_sheet_complete' => collect($closingRows)->pluck('account_type')->intersect(['asset', 'liability', 'equity'])->unique()->count() === 3, 'cash_flow_supported' => $cashRows->isNotEmpty(), 'cash_flow_complete' => $cashRows->isNotEmpty() && $cashFlow['unclassified'] === 0, 'equity_statement_complete' => collect($closingRows)->contains(fn (array $row): bool => self::text($row['account_type']) === 'equity'), 'source_scope' => 'posted_accounting_journals_and_recorded_treasury_transactions'],
            'accounts' => $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => $this->branches($companyId, $actor),
            'currencies' => $this->db->table('payroll_accounting_journal_lines')->where('company_id', $companyId)->distinct()->orderBy('currency')->pluck('currency')->all(),
            'source_urls' => ['ledger' => $canViewAccounting ? route('admin.finance.financial-statements', $sourceScope) : null, 'journals' => $canViewAccounting ? route('admin.payroll.accounting.journals.index', array_filter(['company_id' => $companyId, 'branch_id' => $branchId])) : null, 'accounts' => $canViewAccounting ? route('admin.payroll.accounting.accounts.index', ['company_id' => $companyId]) : null, 'treasury' => $actor?->can('treasury.view') ? route('admin.finance.treasury', array_filter(['company_id' => $companyId, 'branch_id' => $branchId])) : null],
        ];
    }

    private function postedLines(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $branchId, ?string $currency): Builder
    {
        return $this->db->table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $companyId)->where('l.company_id', $companyId)->where('a.company_id', $companyId)->whereIn('j.status', self::POSTED)
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('j.branch_id', $branchId))
            ->when($dateFrom !== null, fn (Builder $query): Builder => $query->whereDate('j.posting_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query): Builder => $query->whereDate('j.posting_date', '<=', $dateTo))
            ->when($currency !== null, fn (Builder $query): Builder => $query->where('l.currency', $currency));
    }

    /** @return array<int,array<string,mixed>> */
    private function accountBalances(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $branchId, ?string $currency): array
    {
        return $this->postedLines($companyId, $dateFrom, $dateTo, $branchId, $currency)->select('a.code', 'a.account_type')->selectRaw('SUM(l.debit_minor) total_debit_minor, SUM(l.credit_minor) total_credit_minor')->groupBy('a.code', 'a.account_type')->get()->map(static function (object $row): array {
            $values = get_object_vars($row);

            return [
                'code' => self::text($values['code'] ?? null),
                'account_type' => self::text($values['account_type'] ?? null),
                'total_debit_minor' => $values['total_debit_minor'] ?? null,
                'total_credit_minor' => $values['total_credit_minor'] ?? null,
            ];
        })->values()->all();
    }

    private function equityBalance(int $companyId, ?string $dateFrom, ?string $dateTo, ?int $branchId, ?string $currency): int
    {
        return self::integer($this->postedLines($companyId, $dateFrom, $dateTo, $branchId, $currency)->where('a.account_type', 'equity')->selectRaw('COALESCE(SUM(l.credit_minor - l.debit_minor),0) amount')->value('amount'));
    }

    /** @return Collection<int,\stdClass> */
    private function branches(int $companyId, ?User $actor): Collection
    {
        $query = $this->db->table('branches')->where('company_id', $companyId)->orderBy('name');
        if ($actor !== null && ! $actor->can('organization.tenant-all')) {
            $query->whereExists(fn (Builder $access): Builder => $access->selectRaw('1')->from('branch_user_access')->whereColumn('branch_user_access.branch_id', 'branches.id')->where('branch_user_access.user_id', $actor->id));
        }

        return $query->get(['id', 'name']);
    }

    private static function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
