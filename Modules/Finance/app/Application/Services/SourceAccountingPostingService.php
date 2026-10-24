<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SourceAccountingPostingService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{id:int,code:string,name:string,type:string} */
    public function accountEvidence(int $companyId, string $code, string $currency, int $actorId): array
    {
        return $this->account($companyId, $code, $currency, $actorId);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string,cost_center_id?:int|null,cost_center_code_snapshot?:string|null,cost_center_name_snapshot?:string|null}>  $lines
     */
    public function post(int $companyId, ?int $branchId, string $sourceType, int $sourceId, string $postingType, string $date, string $currency, array $lines, int $actorId): int
    {
        return $this->postInternal($companyId, $branchId, $sourceType, $sourceId, $postingType, $date, $currency, $lines, $actorId, null, null, false);
    }

    /**
     * Canonical entry point for source transactions resolved through the generic
     * accounting posting policy foundation.
     *
     * @param  list<array{account_code:string,debit:int,credit:int,description:string,cost_center_id?:int|null,cost_center_code_snapshot?:string|null,cost_center_name_snapshot?:string|null}>  $lines
     */
    public function postPolicyBacked(int $companyId, ?int $branchId, string $sourceType, int $sourceId, string $postingType, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, $sourceType, $sourceId, $postingType, $date, $currency, $lines, $actorId, $policyId, $policyVersion, false, null, false, true);
    }

    /**
     * Canonical policy-backed reversal entry point. The immutable original
     * journal is retained and linked through reversal_of_journal_id.
     *
     * @param  list<array{account_code:string,debit:int,credit:int,description:string,cost_center_id?:int|null,cost_center_code_snapshot?:string|null,cost_center_name_snapshot?:string|null}>  $lines
     */
    public function postPolicyBackedReversal(int $companyId, ?int $branchId, string $sourceType, int $sourceId, string $postingType, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion, int $originalJournalId): int
    {
        $originalExists = $this->db->table('payroll_accounting_journals')->where(['id' => $originalJournalId, 'company_id' => $companyId])->exists();
        if (! $originalExists) {
            throw ValidationException::withMessages(['journal' => 'ACCOUNTING_REVERSAL_SOURCE_INVALID']);
        }

        return $this->postInternal($companyId, $branchId, $sourceType, $sourceId, $postingType, $date, $currency, $lines, $actorId, $policyId, $policyVersion, false, $originalJournalId, false, true);
    }

    public function reversePolicyBackedJournal(int $companyId, ?int $branchId, string $sourceType, int $sourceId, string $postingType, string $date, string $currency, int $actorId, int $originalJournalId): int
    {
        $original = $this->db->table('payroll_accounting_journals')->where(['id' => $originalJournalId, 'company_id' => $companyId])->first();
        if ($original === null) {
            throw ValidationException::withMessages(['journal' => 'ACCOUNTING_REVERSAL_SOURCE_INVALID']);
        }
        $originalLines = $this->db->table('payroll_accounting_journal_lines')->where(['journal_id' => $originalJournalId, 'company_id' => $companyId])->orderBy('line_number')->get();
        if ($originalLines->isEmpty()) {
            throw ValidationException::withMessages(['journal' => 'ACCOUNTING_REVERSAL_SOURCE_INVALID']);
        }
        $policyId = null;
        $policyVersion = null;
        $lines = array_values($originalLines->map(function (object $line) use (&$policyId, &$policyVersion): array {
            $snapshot = json_decode($this->text($line->mapping_snapshot ?? null), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot)) {
                throw ValidationException::withMessages(['journal' => 'ACCOUNTING_REVERSAL_POLICY_EVIDENCE_INVALID']);
            }
            $policyId ??= $this->nullableInteger($snapshot['policy_id'] ?? null);
            $policyVersion ??= $this->nullableInteger($snapshot['policy_version'] ?? null);

            return ['account_code' => $this->text($line->account_code_snapshot ?? null), 'debit' => $this->integer($line->credit_minor ?? null), 'credit' => $this->integer($line->debit_minor ?? null), 'description' => 'Reversal: '.$this->text($line->description ?? null)];
        })->all());
        if ($policyId === null || $policyVersion === null) {
            throw ValidationException::withMessages(['journal' => 'ACCOUNTING_REVERSAL_POLICY_EVIDENCE_INVALID']);
        }

        return $this->postInternal($companyId, $branchId, $sourceType, $sourceId, $postingType, $date, $currency, $lines, $actorId, $policyId, $policyVersion, false, $originalJournalId);
    }

    /**
     * Dedicated policy-backed path. A zero opening amount is valid evidence and
     * must remain distinguishable from a missing opening basis.
     *
     * @param  list<array{account_code:string,debit:int,credit:int,description:string}>  $lines
     */
    public function postTreasuryOpeningBalance(int $companyId, ?int $branchId, int $openingBalanceId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, 'treasury_opening_balance', $openingBalanceId, 'treasury_opening_balance', $date, $currency, $lines, $actorId, $policyId, $policyVersion, true);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string}>  $lines
     */
    public function postTreasuryInternalTransfer(int $companyId, ?int $branchId, int $transferId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, 'treasury_internal_transfer', $transferId, 'treasury_internal_transfer', $date, $currency, $lines, $actorId, $policyId, $policyVersion, false);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string}>  $lines
     */
    public function postTreasuryInternalTransferReversal(int $companyId, ?int $branchId, int $transferId, int $originalJournalId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, 'treasury_internal_transfer', $transferId, 'treasury_internal_transfer_reversal', $date, $currency, $lines, $actorId, $policyId, $policyVersion, false, $originalJournalId);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string}>  $lines
     */
    public function postCustomerCreditNote(int $companyId, ?int $branchId, int $creditNoteId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, 'customer_credit_note', $creditNoteId, 'customer_credit_note', $date, $currency, $lines, $actorId, $policyId, $policyVersion, false);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string}>  $lines
     */
    public function postCustomerRefund(int $companyId, ?int $branchId, int $refundId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, 'customer_refund', $refundId, 'customer_refund', $date, $currency, $lines, $actorId, $policyId, $policyVersion, false);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string}>  $lines
     */
    public function postInventoryAdjustment(int $companyId, int $branchId, int $adjustmentId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, 'inventory_adjustment', $adjustmentId, 'inventory_adjustment', $date, $currency, $lines, $actorId, $policyId, $policyVersion, false);
    }

    /** @param list<array{account_code:string,debit:int,credit:int,description:string}> $lines */
    public function postAssetLifecycle(int $companyId, int $branchId, string $sourceType, int $sourceId, string $date, string $currency, array $lines, int $actorId, int $policyId, int $policyVersion): int
    {
        return $this->postInternal($companyId, $branchId, $sourceType, $sourceId, $sourceType, $date, $currency, $lines, $actorId, $policyId, $policyVersion, false);
    }

    /** @param list<array{account_code:string,debit:int,credit:int,description:string}> $lines */
    public function postFiscalYearClose(int $companyId, int $closeRequestId, string $date, string $currency, array $lines, int $actorId): int
    {
        return $this->postInternal($companyId, null, 'fiscal_year_close', $closeRequestId, 'fiscal_year_close', $date, $currency, $lines, $actorId, null, null, false, null, true);
    }

    /**
     * @param  list<array{account_code:string,debit:int,credit:int,description:string,cost_center_id?:int|null,cost_center_code_snapshot?:string|null,cost_center_name_snapshot?:string|null}>  $lines
     */
    private function postInternal(int $companyId, ?int $branchId, string $sourceType, int $sourceId, string $postingType, string $date, string $currency, array $lines, int $actorId, ?int $policyId, ?int $policyVersion, bool $allowZero, ?int $reversalOfJournalId = null, bool $allowClosedPeriod = false, bool $validatePolicyMapping = false): int
    {
        return $this->db->transaction(function () use ($companyId, $branchId, $sourceType, $sourceId, $postingType, $date, $currency, $lines, $actorId, $policyId, $policyVersion, $allowZero, $reversalOfJournalId, $allowClosedPeriod, $validatePolicyMapping): int {
            $this->validatePostingContext($companyId, $branchId, $date, $currency, $policyId, $policyVersion);
            if ($validatePolicyMapping) {
                $this->validatePolicyLineMapping($companyId, $policyId, $lines);
            }
            $key = implode(':', [$companyId, $sourceType, $sourceId, $postingType]);
            $existing = $this->db->table('payroll_accounting_journals')->where('generic_source_key', $key)->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $periodId = $this->openPeriod($companyId, $date, $allowClosedPeriod);
            $debit = array_sum(array_column($lines, 'debit'));
            $credit = array_sum(array_column($lines, 'credit'));
            if ((! $allowZero && $debit <= 0) || $debit < 0 || $debit !== $credit) {
                throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_DEBIT_CREDIT_IMBALANCE']);
            }
            $journalId = (int) $this->db->table('payroll_accounting_journals')->insertGetId([
                'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'branch_id' => $branchId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'posting_type' => $postingType, 'generic_source_key' => $key,
                // This legacy column is constrained to payroll_accounting_policies.
                // Generic policy lineage is retained on each immutable journal line.
                'payroll_run_id' => null, 'payroll_period_id' => null, 'accounting_period_id' => $periodId, 'accounting_policy_id' => null, 'policy_version' => null,
                'journal_number' => strtoupper(substr($postingType, 0, 10)).'-'.$sourceId.'-'.substr(hash('sha256', $key), 0, 8), 'journal_date' => $date, 'posting_date' => $date, 'resolution_date' => $date, 'currency' => $currency, 'calculation_version' => 1,
                'status' => 'internally_posted', 'total_debit_minor' => $debit, 'total_credit_minor' => $credit, 'line_count' => count($lines), 'active_intent_key' => null, 'idempotency_key' => (string) Str::uuid(), 'version' => 1,
                'reversal_of_journal_id' => $reversalOfJournalId, 'created_by' => $actorId, 'posted_by' => $actorId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $index => $line) {
                $account = $this->account($companyId, $line['account_code'], $currency, $actorId);
                $dimension = $this->costCenterDimension($companyId, $account['type'], $line);
                $lineId = (int) $this->db->table('payroll_accounting_journal_lines')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'journal_id' => $journalId, 'line_number' => $index + 1,
                    'source_type' => $sourceType, 'source_code' => $postingType, 'description' => $line['description'], 'account_id' => $account['id'],
                    'account_code_snapshot' => $account['code'], 'account_name_snapshot' => $account['name'], 'debit_minor' => $line['debit'], 'credit_minor' => $line['credit'], 'currency' => $currency,
                    'cost_center_id' => $dimension['id'], 'cost_center_code_snapshot' => $dimension['code'], 'cost_center_name_snapshot' => $dimension['name'], 'organization_unit_id' => null, 'organization_unit_code_snapshot' => null, 'organization_unit_name_snapshot' => null,
                    'mapping_id' => null, 'mapping_snapshot' => json_encode(['policy' => $policyId === null ? 'canonical_source_posting_v1' : 'accounting_posting_policy', 'policy_id' => $policyId, 'policy_version' => $policyVersion], JSON_THROW_ON_ERROR), 'source_count' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->db->table('accounting_journal_source_evidence')->insert([
                    'company_id' => $companyId, 'journal_id' => $journalId, 'journal_line_id' => $lineId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'posting_type' => $postingType,
                    'amount_minor' => max($line['debit'], $line['credit']), 'safe_snapshot' => json_encode(['date' => $date, 'currency' => $currency, 'cost_center_id' => $dimension['id'], 'cost_center_code' => $dimension['code'], 'cost_center_name' => $dimension['name']], JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
            }

            return $journalId;
        }, 3);
    }

    private function validatePostingContext(int $companyId, ?int $branchId, string $date, string $currency, ?int $policyId, ?int $policyVersion): void
    {
        if (! $this->db->table('companies')->where('id', $companyId)->exists()) {
            throw ValidationException::withMessages(['company_id' => 'ACCOUNTING_COMPANY_INVALID']);
        }
        if ($branchId !== null && ! $this->db->table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'ACCOUNTING_BRANCH_TENANT_MISMATCH']);
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['posting_date' => 'INVALID_POSTING_DATE']);
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['currency' => 'ACCOUNTING_CURRENCY_INVALID']);
        }
        if (Schema::hasTable('accounting_fiscal_years') && $this->db->table('accounting_fiscal_years')->where('company_id', $companyId)->exists()) {
            $fiscalYear = $this->db->table('accounting_fiscal_years')->where('company_id', $companyId)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->lockForUpdate()->first();
            if ($fiscalYear === null) {
                throw ValidationException::withMessages(['posting_date' => 'INVALID_POSTING_DATE']);
            }
            if ((get_object_vars($fiscalYear)['status'] ?? null) === 'closed') {
                throw ValidationException::withMessages(['fiscal_year' => 'FISCAL_YEAR_CLOSED']);
            }
        }
        if (($policyId === null) !== ($policyVersion === null)) {
            throw ValidationException::withMessages(['accounting_policy' => 'ACCOUNTING_POSTING_POLICY_LINEAGE_INCOMPLETE']);
        }
        if ($policyId !== null && ! $this->db->table('accounting_posting_policies')->where(['id' => $policyId, 'company_id' => $companyId, 'version' => $policyVersion, 'currency' => $currency, 'status' => 'approved'])->whereDate('effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->exists()) {
            throw ValidationException::withMessages(['accounting_policy' => 'ACCOUNTING_POSTING_POLICY_INVALID']);
        }
    }

    /** @param list<array{account_code:string,debit:int,credit:int,description:string,cost_center_id?:int|null,cost_center_code_snapshot?:string|null,cost_center_name_snapshot?:string|null}> $lines */
    private function validatePolicyLineMapping(int $companyId, ?int $policyId, array $lines): void
    {
        if ($policyId === null) {
            return;
        }
        $expected = $this->db->table('accounting_posting_policy_lines as line')
            ->join('accounting_accounts as account', 'account.id', '=', 'line.account_id')
            ->where(['line.accounting_posting_policy_id' => $policyId, 'account.company_id' => $companyId])
            ->orderBy('line.sequence')
            ->get(['line.entry_side', 'account.code'])
            ->map(fn (object $line): string => $this->text($line->entry_side ?? null).':'.$this->text($line->code ?? null))
            ->values()->all();
        $actual = array_map(function (array $line): string {
            $side = $line['debit'] > 0 && $line['credit'] === 0 ? 'debit' : ($line['credit'] > 0 && $line['debit'] === 0 ? 'credit' : 'invalid');

            return $side.':'.$line['account_code'];
        }, $lines);
        if ($expected === [] || $actual !== $expected) {
            throw ValidationException::withMessages(['accounting_policy' => 'ACCOUNTING_POSTING_POLICY_MAPPING_MISMATCH']);
        }
    }

    private function openPeriod(int $companyId, string $date, bool $allowClosedPeriod = false): int
    {
        $period = $this->db->table('accounting_periods')->where('company_id', $companyId)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->lockForUpdate()->first();
        if ($period === null) {
            $year = (int) substr($date, 0, 4);
            $month = (int) substr($date, 5, 2);
            $start = sprintf('%04d-%02d-01', $year, $month);
            $timestamp = strtotime($start);
            if ($timestamp === false) {
                throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_DATE_INVALID']);
            }
            $end = date('Y-m-t', $timestamp);
            $this->db->table('accounting_periods')->insertOrIgnore(['company_id' => $companyId, 'year' => $year, 'month' => $month, 'start_date' => $start, 'end_date' => $end, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $period = $this->db->table('accounting_periods')->where(['company_id' => $companyId, 'year' => $year, 'month' => $month])->lockForUpdate()->firstOrFail();
        }
        $values = get_object_vars($period);
        if (($values['status'] ?? null) !== 'open' && ! ($allowClosedPeriod && ($values['status'] ?? null) === 'closed')) {
            throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_CLOSED']);
        }

        return $this->integer($values['id'] ?? null);
    }

    /** @return array{id:int,code:string,name:string,type:string} */
    private function account(int $companyId, string $code, string $currency, int $actorId): array
    {
        $definitions = [
            '1100' => ['Payment Clearing', 'asset', 'debit'], '1150' => ['Accounts Receivable', 'asset', 'debit'], '1200' => ['Inventory Asset', 'asset', 'debit'],
            '2100' => ['Accounts Payable', 'liability', 'credit'], '2150' => ['Goods Received Not Invoiced', 'liability', 'credit'],
            '4000' => ['Sales Revenue', 'revenue', 'credit'], '5000' => ['Cost of Goods Sold', 'expense', 'debit'],
            '5100' => ['Operating Expense', 'expense', 'debit'], '5200' => ['Depreciation Expense', 'expense', 'debit'], '5300' => ['Asset Disposal Loss', 'expense', 'debit'],
            '1300' => ['Fixed Assets', 'asset', 'debit'], '1390' => ['Accumulated Depreciation', 'contra_asset', 'credit'],
        ];
        $row = $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'code' => $code, 'currency' => $currency, 'allow_posting' => true])->where('status', 'active')->first();
        if ($row === null) {
            if (! isset($definitions[$code])) {
                throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_ACCOUNT_PURPOSE_UNKNOWN: '.$code]);
            }
            [$name, $type, $normal] = $definitions[$code];
            $this->db->table('accounting_accounts')->insertOrIgnore(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'parent_id' => null, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => $currency, 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2000-01-01', 'effective_to' => null, 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $row = $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'code' => $code])->firstOrFail();
        }
        $values = get_object_vars($row);

        return ['id' => $this->integer($values['id'] ?? null), 'code' => $this->text($values['code'] ?? null), 'name' => $this->text($values['name'] ?? null), 'type' => $this->text($values['account_type'] ?? null)];
    }

    /**
     * @param  array{account_code:string,debit:int,credit:int,description:string,cost_center_id?:int|null,cost_center_code_snapshot?:string|null,cost_center_name_snapshot?:string|null}  $line
     * @return array{id:?int,code:?string,name:?string}
     */
    private function costCenterDimension(int $companyId, string $accountType, array $line): array
    {
        $id = $line['cost_center_id'] ?? null;
        if ($id === null) {
            return ['id' => null, 'code' => null, 'name' => null];
        }
        if (! in_array($accountType, ['expense', 'asset'], true)) {
            throw ValidationException::withMessages(['cost_center_id' => 'Cost Center chỉ được gắn lên dòng Budget consumption (expense/asset).']);
        }
        $center = $this->db->table('cost_centers')->where(['id' => $id, 'company_id' => $companyId])->firstOrFail(['id', 'code', 'name']);
        $values = get_object_vars($center);

        return [
            'id' => $this->integer($values['id'] ?? null),
            'code' => $this->nullableSnapshot($line['cost_center_code_snapshot'] ?? null) ?? $this->text($values['code'] ?? null),
            'name' => $this->nullableSnapshot($line['cost_center_name_snapshot'] ?? null) ?? $this->text($values['name'] ?? null),
        ];
    }

    private function nullableSnapshot(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid accounting integer.'));
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : $this->integer($value);
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid accounting text.');
    }
}
