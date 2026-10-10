<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;

final readonly class VendorRefundService
{
    public function __construct(
        private ConnectionInterface $db,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
        private TreasuryService $treasury,
        private VendorRefundFxSettlementService $fxSettlement,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $key = $this->text($data['idempotency_key'] ?? null, 'idempotency_key');
            $existing = $this->db->table('supplier_refund_receipts')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $receivableId = $this->positive($data['vendor_refund_receivable_id'] ?? null, 'vendor_refund_receivable_id');
            $receivable = $this->receivable($companyId, $receivableId, true);
            $amount = $this->positive($data['amount_minor'] ?? null, 'amount_minor');
            $reserved = (int) $this->db->table('supplier_refund_receipts')->where('vendor_refund_receivable_id', $receivableId)->whereNotIn('status', ['cancelled', 'rejected'])->lockForUpdate()->sum('amount_minor');
            if ($amount > $this->int($receivable, 'original_minor') - $reserved) {
                throw ValidationException::withMessages(['amount_minor' => 'SUPPLIER_REFUND_EXCEEDS_REMAINING_RECEIVABLE']);
            }
            $accountId = $this->positive($data['financial_account_id'] ?? null, 'financial_account_id');
            $account = $this->refundAccount($companyId, $accountId, $this->int($receivable, 'branch_id'), $this->text($data['refund_date'] ?? null, 'refund_date'));
            if ($account['currency'] !== $this->value($receivable, 'currency')) {
                throw ValidationException::withMessages(['financial_account_id' => 'SUPPLIER_REFUND_CURRENCY_MISMATCH']);
            }

            return (int) $this->db->table('supplier_refund_receipts')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $this->int($receivable, 'branch_id'),
                'vendor_id' => $this->int($receivable, 'vendor_id'),
                'vendor_refund_receivable_id' => $receivableId,
                'financial_account_id' => $accountId,
                'reference' => $this->text($data['reference'] ?? null, 'reference'),
                'refund_date' => $this->text($data['refund_date'] ?? null, 'refund_date'),
                'currency' => $this->value($receivable, 'currency'),
                'amount_minor' => $amount,
                'status' => 'draft',
                'idempotency_key' => $key,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function submit(int $companyId, int $receiptId, User $actor): int
    {
        return $this->transition($companyId, $receiptId, $actor, 'draft', 'submitted', ['submitted_by' => $actor->id, 'submitted_at' => now()]);
    }

    public function approve(int $companyId, int $receiptId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $receiptId, $actor): int {
            $receipt = $this->receipt($companyId, $receiptId, true);
            if ($this->value($receipt, 'status') === 'approved') {
                return $receiptId;
            }
            if ($this->value($receipt, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'SUPPLIER_REFUND_APPROVAL_STATE_INVALID']);
            }
            if ($this->int($receipt, 'created_by') === (int) $actor->id) {
                throw ValidationException::withMessages(['actor' => 'SUPPLIER_REFUND_MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('supplier_refund_receipts')->where('id', $receiptId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $receiptId;
        }, 3);
    }

    public function post(int $companyId, int $receiptId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $receiptId, $actor): int {
            $receipt = $this->receipt($companyId, $receiptId, true);
            if ($this->value($receipt, 'status') === 'posted') {
                return $receiptId;
            }
            if ($this->value($receipt, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'SUPPLIER_REFUND_POSTING_STATE_INVALID']);
            }
            $receivable = $this->receivable($companyId, $this->int($receipt, 'vendor_refund_receivable_id'), true);
            $amount = $this->int($receipt, 'amount_minor');
            if ($amount > $this->int($receivable, 'original_minor') - $this->int($receivable, 'refunded_minor')) {
                throw ValidationException::withMessages(['amount_minor' => 'SUPPLIER_REFUND_EXCEEDS_REMAINING_RECEIVABLE']);
            }
            $functionalCurrency = $this->db->table('companies')->where('id', $companyId)->value('functional_currency_code');
            if (! is_string($functionalCurrency) || preg_match('/^[A-Z]{3}$/', strtoupper($functionalCurrency)) !== 1) {
                throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
            }
            $foreign = strtoupper($this->value($receipt, 'currency')) !== strtoupper($functionalCurrency);
            $preparedFx = $foreign ? $this->fxSettlement->prepare($companyId, $receipt, $receivable) : null;
            $account = $this->refundAccount($companyId, $this->int($receipt, 'financial_account_id'), $this->int($receipt, 'branch_id'), $this->value($receipt, 'refund_date'));
            $realized = $preparedFx['realized_fx_minor'] ?? 0;
            $policy = $foreign
                ? $this->refundFxPolicy($companyId, $this->value($receipt, 'refund_date'), strtoupper($functionalCurrency), $realized)
                : $this->refundPolicy($companyId, $this->value($receipt, 'refund_date'), $this->value($receipt, 'currency'));
            $cash = $this->policyAccount($policy, 'CASH', 'debit');
            $receivableAccount = $this->policyAccount($policy, 'RECEIVABLE', 'credit');
            if ($cash !== $account['ledger_code']) {
                throw ValidationException::withMessages(['financial_account_id' => 'SUPPLIER_REFUND_CASH_POLICY_MISMATCH']);
            }
            $journalLines = [['account_code' => $cash, 'debit' => $preparedFx['functional_cash_minor'] ?? $amount, 'credit' => 0, 'description' => 'Supplier refund received']];
            if ($realized < 0) {
                $journalLines[] = ['account_code' => $this->policyAccount($policy, 'FX_LOSS', 'debit'), 'debit' => abs($realized), 'credit' => 0, 'description' => 'Realized FX loss on supplier refund'];
            }
            $journalLines[] = ['account_code' => $receivableAccount, 'debit' => 0, 'credit' => $preparedFx['released_carrying_minor'] ?? $amount, 'description' => 'Settle vendor refund receivable'];
            if ($realized > 0) {
                $journalLines[] = ['account_code' => $this->policyAccount($policy, 'FX_GAIN', 'credit'), 'debit' => 0, 'credit' => $realized, 'description' => 'Realized FX gain on supplier refund'];
            }
            if ($preparedFx !== null) {
                $preparedFx = $this->fxSettlement->captureSnapshot($companyId, $receiptId, $preparedFx);
            }
            $journalId = $this->accounting->postPolicyBacked($companyId, $this->int($receipt, 'branch_id'), 'supplier_refund_receipt', $receiptId, 'supplier_refund_received', $this->value($receipt, 'refund_date'), $preparedFx['functional_currency'] ?? $this->value($receipt, 'currency'), $journalLines, (int) $actor->id, $policy['policy_id'], $policy['version']);
            $treasuryId = $this->treasury->recordSourceToAccount($companyId, $this->int($receipt, 'branch_id'), $this->int($receipt, 'financial_account_id'), 'supplier_refund_receipt', $receiptId, $journalId, $this->value($receipt, 'reference'), $amount, $this->value($receipt, 'refund_date').' 12:00:00', (int) $actor->id, 'inflow');
            if ($preparedFx !== null) {
                $this->fxSettlement->record($companyId, $receiptId, $this->int($receivable, 'id'), $policy['policy_id'], $policy['version'], $journalId, $treasuryId, (int) $actor->id, $preparedFx);
            }
            $refunded = $this->int($receivable, 'refunded_minor') + $amount;
            $status = $refunded === $this->int($receivable, 'original_minor') ? 'refunded' : 'partially_refunded';
            $this->db->table('vendor_refund_receivables')->where('id', $this->int($receivable, 'id'))->update(['refunded_minor' => $refunded, 'status' => $status, 'updated_at' => now()]);
            $this->db->table('supplier_refund_receipts')->where('id', $receiptId)->update(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now(), 'accounting_journal_id' => $journalId, 'treasury_transaction_id' => $treasuryId, 'updated_at' => now()]);

            return $receiptId;
        }, 3);
    }

    /** @param array<string,mixed> $evidence */
    private function transition(int $companyId, int $receiptId, User $actor, string $from, string $to, array $evidence): int
    {
        return $this->db->transaction(function () use ($companyId, $receiptId, $actor, $from, $to, $evidence): int {
            $receipt = $this->receipt($companyId, $receiptId, true);
            if ($this->value($receipt, 'status') === $to) {
                return $receiptId;
            }
            if ($this->value($receipt, 'status') !== $from || ($from === 'draft' && $this->int($receipt, 'created_by') !== (int) $actor->id)) {
                throw ValidationException::withMessages(['status' => 'SUPPLIER_REFUND_TRANSITION_INVALID']);
            }
            $this->db->table('supplier_refund_receipts')->where('id', $receiptId)->update([...$evidence, 'status' => $to, 'updated_at' => now()]);

            return $receiptId;
        }, 3);
    }

    /** @param array{lines:list<array{line_role:string,entry_side:string,account_code:string}>} $policy */
    private function policyAccount(array $policy, string $role, string $side): string
    {
        foreach ($policy['lines'] as $line) {
            if ($line['line_role'] === $role) {
                if ($line['entry_side'] !== $side) {
                    throw ValidationException::withMessages(['accounting_policy' => 'SUPPLIER_REFUND_POSTING_POLICY_INVALID']);
                }

                return $line['account_code'];
            }
        }
        throw ValidationException::withMessages(['accounting_policy' => 'SUPPLIER_REFUND_POSTING_POLICY_INVALID']);
    }

    /** @return array{id:int,ledger_code:string,currency:string} */
    private function refundAccount(int $companyId, int $accountId, int $branchId, string $date): array
    {
        try {
            return $this->treasury->accountEvidence($companyId, $accountId, $branchId, $date);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['financial_account_id' => 'SUPPLIER_REFUND_TREASURY_ACCOUNT_INVALID']);
        }
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function refundPolicy(int $companyId, string $date, string $currency): array
    {
        try {
            return $this->policies->resolve($companyId, 'SUPPLIER_REFUND_RECEIVED', 'vendor_refund', $date, $currency);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $code = $message === 'ACCOUNTING_POSTING_POLICY_MISSING'
                ? 'SUPPLIER_REFUND_POSTING_POLICY_MISSING'
                : 'SUPPLIER_REFUND_POSTING_POLICY_INVALID';
            throw ValidationException::withMessages(['accounting_policy' => $code]);
        }
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function refundFxPolicy(int $companyId, string $date, string $currency, int $realized): array
    {
        $event = $realized > 0 ? 'SUPPLIER_REFUND_FX_GAIN' : ($realized < 0 ? 'SUPPLIER_REFUND_FX_LOSS' : 'SUPPLIER_REFUND_RECEIVED');
        try {
            return $this->policies->resolve($companyId, $event, 'vendor_refund', $date, $currency);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $code = $message === 'ACCOUNTING_POSTING_POLICY_MISSING'
                ? 'SUPPLIER_REFUND_FX_POLICY_MISSING'
                : 'SUPPLIER_REFUND_FX_POLICY_INVALID';
            throw ValidationException::withMessages(['accounting_policy' => $code]);
        }
    }

    private function receivable(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('vendor_refund_receivables')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function receipt(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('supplier_refund_receipts')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function positive(mixed $value, string $field): int
    {
        $number = is_numeric($value) ? (int) $value : 0;

        return $number > 0 ? $number : throw ValidationException::withMessages([$field => 'Giá trị phải lớn hơn 0.']);
    }

    private function int(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid supplier refund integer: '.$field);
    }

    private function value(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid supplier refund text: '.$field);
    }

    private function text(mixed $value, string $field): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages([$field => 'Giá trị bắt buộc.']);
    }
}
