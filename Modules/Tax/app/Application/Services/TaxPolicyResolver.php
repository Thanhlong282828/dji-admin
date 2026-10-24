<?php

namespace Modules\Tax\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final readonly class TaxPolicyResolver
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string, mixed> */
    public function resolve(int $companyId, string $transactionType, string $date, string $categoryCode, string $currency, string $countryCode = 'VN'): array
    {
        if (! Schema::hasTable('vat_policies')) {
            throw ValidationException::withMessages(['tax_policy' => 'TAX_POLICY_SCHEMA_NOT_AVAILABLE']);
        }
        $direction = match ($transactionType) {
            'customer_invoice', 'customer_credit_note' => 'output',
            'vendor_bill', 'finance_expense', 'purchase' => 'input',
            default => throw ValidationException::withMessages(['transaction_type' => 'TAX_TRANSACTION_TYPE_INVALID']),
        };
        $rows = $this->db->table('vat_policies as policy')->join('tax_categories as category', 'category.id', '=', 'policy.tax_category_id')->join('accounting_accounts as account', 'account.id', '=', 'policy.tax_account_id')->join('accounting_posting_policies as posting', 'posting.id', '=', 'policy.accounting_posting_policy_id')
            ->where(['policy.company_id' => $companyId, 'category.company_id' => $companyId, 'category.code' => strtoupper($categoryCode), 'category.direction' => $direction, 'category.country_code' => strtoupper($countryCode), 'category.status' => 'active', 'policy.currency' => strtoupper($currency), 'policy.country_code' => strtoupper($countryCode), 'policy.status' => 'approved', 'account.company_id' => $companyId, 'account.status' => 'active', 'account.allow_posting' => true, 'posting.company_id' => $companyId, 'posting.status' => 'approved'])
            ->whereDate('policy.effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('policy.effective_to')->orWhereDate('policy.effective_to', '>=', $date))->orderByDesc('policy.version')
            ->get(['policy.id', 'policy.policy_key', 'policy.version', 'policy.rate_basis_points', 'policy.effective_from', 'policy.effective_to', 'policy.tax_account_id', 'policy.accounting_posting_policy_id', 'category.id as category_id', 'category.code as category_code', 'category.name as category_name', 'category.direction', 'account.code as tax_account_code', 'account.name as tax_account_name', 'posting.version as accounting_policy_version']);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['tax_policy' => 'TAX_POLICY_NOT_FOUND']);
        }
        if ($rows->count() !== 1) {
            throw ValidationException::withMessages(['tax_policy' => 'TAX_POLICY_AMBIGUOUS']);
        }
        $row = $rows->first();
        $rate = $this->integer($row->rate_basis_points ?? null);
        if ($rate > 10_000) {
            throw ValidationException::withMessages(['tax_rate' => 'TAX_RATE_INVALID']);
        }

        $accountingPolicyId = $this->integer($row->accounting_posting_policy_id ?? null);
        $accountingLines = $this->db->table('accounting_posting_policy_lines as line')
            ->join('accounting_accounts as account', 'account.id', '=', 'line.account_id')
            ->where(['line.accounting_posting_policy_id' => $accountingPolicyId, 'account.company_id' => $companyId, 'account.status' => 'active', 'account.allow_posting' => true])
            ->orderBy('line.sequence')
            ->get(['line.sequence', 'line.entry_side', 'line.line_role', 'line.account_id', 'account.code as account_code'])
            ->map(fn (object $line): array => [
                'sequence' => $this->integer($line->sequence ?? null),
                'entry_side' => $this->text($line->entry_side ?? null),
                'line_role' => $this->text($line->line_role ?? null),
                'account_id' => $this->integer($line->account_id ?? null),
                'account_code' => $this->text($line->account_code ?? null),
            ])->all();
        if (count($accountingLines) !== 3) {
            throw ValidationException::withMessages(['tax_mapping' => 'TAX_MAPPING_REQUIRED']);
        }

        return ['policy_id' => $this->integer($row->id ?? null), 'policy_key' => $this->text($row->policy_key ?? null), 'version' => $this->integer($row->version ?? null), 'category_id' => $this->integer($row->category_id ?? null), 'category_code' => $this->text($row->category_code ?? null), 'category_name' => $this->text($row->category_name ?? null), 'direction' => $this->text($row->direction ?? null), 'rate_basis_points' => $rate, 'tax_account_id' => $this->integer($row->tax_account_id ?? null), 'tax_account_code' => $this->text($row->tax_account_code ?? null), 'tax_account_name' => $this->text($row->tax_account_name ?? null), 'accounting_policy_id' => $accountingPolicyId, 'accounting_policy_version' => $this->integer($row->accounting_policy_version ?? null), 'accounting_lines' => $accountingLines, 'effective_from' => $this->text($row->effective_from ?? null), 'effective_to' => is_string($row->effective_to ?? null) ? $row->effective_to : null, 'currency' => strtoupper($currency), 'country_code' => strtoupper($countryCode)];
    }

    public function calculate(int $taxableAmountMinor, int $rateBasisPoints): int
    {
        if ($taxableAmountMinor < 0 || $rateBasisPoints < 0 || $rateBasisPoints > 10_000) {
            throw ValidationException::withMessages(['tax' => 'TAX_CALCULATION_INPUT_INVALID']);
        }

        return intdiv(($taxableAmountMinor * $rateBasisPoints) + 5_000, 10_000);
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return list<array{account_code:string,debit:int,credit:int,description:string}>
     */
    public function postingLines(array $policy, int $taxableAmountMinor, int $taxAmountMinor, string $description): array
    {
        $direction = $policy['direction'] ?? null;
        $taxAccountId = $policy['tax_account_id'] ?? null;
        $accountingLines = $policy['accounting_lines'] ?? null;
        if (! in_array($direction, ['output', 'input'], true) || ! is_int($taxAccountId) || ! is_array($accountingLines)) {
            throw ValidationException::withMessages(['tax_mapping' => 'TAX_MAPPING_REQUIRED']);
        }
        $total = $taxableAmountMinor + $taxAmountMinor;
        $taxMappingCount = 0;
        $lines = [];
        foreach ($accountingLines as $line) {
            if (! is_array($line) || ! is_int($line['account_id'] ?? null) || ! is_string($line['entry_side'] ?? null) || ! is_string($line['account_code'] ?? null) || ! is_string($line['line_role'] ?? null)) {
                throw ValidationException::withMessages(['tax_mapping' => 'TAX_MAPPING_REQUIRED']);
            }
            $isTax = $line['account_id'] === $taxAccountId;
            $taxMappingCount += $isTax ? 1 : 0;
            $amount = match ([$direction, $line['entry_side'], $isTax]) {
                ['output', 'debit', false], ['input', 'credit', false] => $total,
                ['output', 'credit', true], ['input', 'debit', true] => $taxAmountMinor,
                ['output', 'credit', false], ['input', 'debit', false] => $taxableAmountMinor,
                default => throw ValidationException::withMessages(['tax_mapping' => 'TAX_MAPPING_REQUIRED']),
            };
            $lines[] = ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $amount : 0, 'credit' => $line['entry_side'] === 'credit' ? $amount : 0, 'description' => $description.' '.$line['line_role']];
        }
        if ($taxMappingCount !== 1 || array_sum(array_column($lines, 'debit')) !== array_sum(array_column($lines, 'credit'))) {
            throw ValidationException::withMessages(['tax_mapping' => 'TAX_MAPPING_REQUIRED']);
        }

        return $lines;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid tax policy integer.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid tax policy text.');
    }
}
