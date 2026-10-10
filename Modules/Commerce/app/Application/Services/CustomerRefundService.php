<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;

final readonly class CustomerRefundService
{
    public function __construct(
        private ConnectionInterface $db,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
        private TreasuryService $treasury,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $key = $this->text($data['idempotency_key'] ?? null, 'idempotency_key');
            $existing = $this->db->table('customer_refunds')->where(['company_id' => $companyId, 'idempotency_key' => $key])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $inputs = $data['allocations'] ?? null;
            if (! is_array($inputs) || $inputs === []) {
                throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_ALLOCATIONS_REQUIRED']);
            }
            $allocations = [];
            foreach ($inputs as $input) {
                if (! is_array($input)) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_ALLOCATION_INVALID']);
                }
                $noteId = $this->positive($input['customer_credit_note_id'] ?? null, 'customer_credit_note_id');
                if (isset($allocations[$noteId])) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_ALLOCATION_DUPLICATE']);
                }
                $allocations[$noteId] = $this->positive($input['allocated_minor'] ?? null, 'allocated_minor');
            }
            ksort($allocations, SORT_NUMERIC);
            $notes = [];
            foreach ($allocations as $noteId => $amount) {
                $note = $this->creditNote($companyId, $noteId, true);
                if ($this->value($note, 'status') !== 'posted' || $this->int($note, 'customer_credit_liability_minor') <= 0) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_CREDIT_NOTE_NOT_REFUNDABLE']);
                }
                $reserved = $this->allocatedToNote($noteId, false);
                if ($amount > $this->int($note, 'customer_credit_liability_minor') - $reserved) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_EXCEEDS_AVAILABLE_LIABILITY']);
                }
                $notes[$noteId] = $note;
            }
            $existing = $this->db->table('customer_refunds')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $first = reset($notes);
            if (! is_object($first)) {
                throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_ALLOCATIONS_REQUIRED']);
            }
            $branchId = $this->int($first, 'branch_id');
            $customerId = $this->int($first, 'customer_id');
            $currency = $this->value($first, 'currency');
            foreach ($notes as $note) {
                if ($this->int($note, 'branch_id') !== $branchId || $this->int($note, 'customer_id') !== $customerId) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_SCOPE_MISMATCH']);
                }
                if ($this->value($note, 'currency') !== $currency) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_CURRENCY_MISMATCH']);
                }
            }
            $amount = array_sum($allocations);
            if (isset($data['amount_minor']) && $this->positive($data['amount_minor'], 'amount_minor') !== $amount) {
                throw ValidationException::withMessages(['amount_minor' => 'CUSTOMER_REFUND_AMOUNT_ALLOCATION_MISMATCH']);
            }
            $accountId = $this->positive($data['financial_account_id'] ?? null, 'financial_account_id');
            $account = $this->refundAccount($companyId, $accountId, $branchId, $this->text($data['refund_date'] ?? null, 'refund_date'));
            if ($account['currency'] !== $currency) {
                throw ValidationException::withMessages(['financial_account_id' => 'CUSTOMER_REFUND_CURRENCY_MISMATCH']);
            }
            $id = (int) $this->db->table('customer_refunds')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $customerId, 'financial_account_id' => $accountId,
                'reference' => $this->text($data['reference'] ?? null, 'reference'), 'refund_date' => $this->text($data['refund_date'] ?? null, 'refund_date'),
                'currency' => $currency, 'amount_minor' => $amount, 'status' => 'draft', 'idempotency_key' => $key,
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($allocations as $noteId => $allocated) {
                $this->db->table('customer_refund_allocations')->insert(['customer_refund_id' => $id, 'customer_credit_note_id' => $noteId, 'allocated_minor' => $allocated, 'created_at' => now(), 'updated_at' => now()]);
            }
            activity('customer-refund')->causedBy($actor)->event('created')->withProperties(['company_id' => $companyId, 'refund_id' => $id, 'amount_minor' => $amount, 'credit_note_ids' => array_keys($allocations)])->log('Customer refund created');

            return $id;
        }, 3);
    }

    public function submit(int $companyId, int $id, User $actor): int
    {
        return $this->transition($companyId, $id, $actor, 'draft', 'submitted');
    }

    public function approve(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $refund = $this->refund($companyId, $id, true);
            if ($this->value($refund, 'status') === 'approved') {
                return $id;
            }
            if ($this->value($refund, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'CUSTOMER_REFUND_TRANSITION_INVALID']);
            }
            if ($this->int($refund, 'created_by') === (int) $actor->id) {
                throw ValidationException::withMessages(['approval' => 'CUSTOMER_REFUND_MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('customer_refunds')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'approved', $companyId, $id);

            return $id;
        }, 3);
    }

    public function post(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $refund = $this->refund($companyId, $id, true);
            if ($this->value($refund, 'status') === 'posted') {
                return $id;
            }
            if ($this->value($refund, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'CUSTOMER_REFUND_TRANSITION_INVALID']);
            }
            $allocationRows = $this->db->table('customer_refund_allocations')->where('customer_refund_id', $id)->orderBy('customer_credit_note_id')->lockForUpdate()->get();
            if ($allocationRows->isEmpty()) {
                throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_ALLOCATIONS_REQUIRED']);
            }
            $total = 0;
            foreach ($allocationRows as $allocation) {
                $noteId = $this->int($allocation, 'customer_credit_note_id');
                $note = $this->creditNote($companyId, $noteId, true);
                if ($this->value($note, 'status') !== 'posted' || $this->int($note, 'customer_id') !== $this->int($refund, 'customer_id') || $this->int($note, 'branch_id') !== $this->int($refund, 'branch_id') || $this->value($note, 'currency') !== $this->value($refund, 'currency')) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_SOURCE_INVALID']);
                }
                $allocated = $this->int($allocation, 'allocated_minor');
                if ($allocated > $this->int($note, 'customer_credit_liability_minor') - $this->allocatedToNote($noteId, true, $id)) {
                    throw ValidationException::withMessages(['allocations' => 'CUSTOMER_REFUND_EXCEEDS_AVAILABLE_LIABILITY']);
                }
                $total += $allocated;
            }
            if ($total !== $this->int($refund, 'amount_minor')) {
                throw ValidationException::withMessages(['amount_minor' => 'CUSTOMER_REFUND_AMOUNT_ALLOCATION_MISMATCH']);
            }
            $account = $this->refundAccount($companyId, $this->int($refund, 'financial_account_id'), $this->int($refund, 'branch_id'), $this->value($refund, 'refund_date'));
            if ($account['currency'] !== $this->value($refund, 'currency')) {
                throw ValidationException::withMessages(['financial_account_id' => 'CUSTOMER_REFUND_CURRENCY_MISMATCH']);
            }
            $policy = $this->refundPolicy($companyId, $this->value($refund, 'refund_date'), $this->value($refund, 'currency'));
            $liability = $this->policyAccount($policy, 'CUSTOMER_CREDIT_LIABILITY', 'debit');
            $cash = $this->policyAccount($policy, 'CASH', 'credit');
            if ($cash !== $account['ledger_code']) {
                throw ValidationException::withMessages(['financial_account_id' => 'CUSTOMER_REFUND_CASH_POLICY_MISMATCH']);
            }
            $journalId = $this->accounting->postCustomerRefund($companyId, $this->int($refund, 'branch_id'), $id, $this->value($refund, 'refund_date'), $this->value($refund, 'currency'), [
                ['account_code' => $liability, 'debit' => $total, 'credit' => 0, 'description' => 'Settle customer credit liability'],
                ['account_code' => $cash, 'debit' => 0, 'credit' => $total, 'description' => 'Customer cash refund'],
            ], (int) $actor->id, $policy['policy_id'], $policy['version']);
            $treasuryId = $this->treasury->recordSourceToAccount($companyId, $this->int($refund, 'branch_id'), $this->int($refund, 'financial_account_id'), 'customer_refund', $id, $journalId, $this->value($refund, 'reference'), $total, $this->value($refund, 'refund_date').' 12:00:00', (int) $actor->id, 'outflow');
            $this->db->table('customer_refunds')->where('id', $id)->update([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now(), 'accounting_policy_id' => $policy['policy_id'],
                'accounting_policy_version' => $policy['version'], 'accounting_journal_id' => $journalId, 'treasury_transaction_id' => $treasuryId, 'updated_at' => now(),
            ]);
            $this->audit($actor, 'posted', $companyId, $id, ['journal_id' => $journalId, 'treasury_transaction_id' => $treasuryId, 'amount_minor' => $total]);

            return $id;
        }, 3);
    }

    private function transition(int $companyId, int $id, User $actor, string $from, string $to): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $id, $actor, $from, $to): int {
            $refund = $this->refund($companyId, $id, true);
            if ($this->value($refund, 'status') === $to) {
                return $id;
            }
            if ($this->value($refund, 'status') !== $from || ($from === 'draft' && $this->int($refund, 'created_by') !== (int) $actor->id)) {
                throw ValidationException::withMessages(['status' => 'CUSTOMER_REFUND_TRANSITION_INVALID']);
            }
            $this->db->table('customer_refunds')->where('id', $id)->update(['status' => $to, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, $to, $companyId, $id);

            return $id;
        }, 3);
    }

    private function allocatedToNote(int $creditNoteId, bool $postedOnly, ?int $excludeRefundId = null): int
    {
        return (int) $this->db->table('customer_refund_allocations as allocation')
            ->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')
            ->where('allocation.customer_credit_note_id', $creditNoteId)
            ->when($postedOnly, fn (Builder $query): Builder => $query->where('refund.status', 'posted'))
            ->when(! $postedOnly, fn (Builder $query): Builder => $query->whereIn('refund.status', ['draft', 'submitted', 'approved', 'posted']))
            ->when($excludeRefundId !== null, fn (Builder $query): Builder => $query->where('refund.id', '<>', $excludeRefundId))
            ->sum('allocation.allocated_minor');
    }

    /** @return array{id:int,ledger_code:string,currency:string} */
    private function refundAccount(int $companyId, int $accountId, int $branchId, string $date): array
    {
        $eligible = $this->db->table('treasury_financial_accounts')->where(['id' => $accountId, 'company_id' => $companyId, 'status' => 'active'])->whereIn('type', ['cash', 'bank'])->where(fn (Builder $query): Builder => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))->exists();
        if (! $eligible) {
            throw ValidationException::withMessages(['financial_account_id' => 'CUSTOMER_REFUND_TREASURY_ACCOUNT_INVALID']);
        }
        try {
            return $this->treasury->accountEvidence($companyId, $accountId, $branchId, $date);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['financial_account_id' => 'CUSTOMER_REFUND_TREASURY_ACCOUNT_INVALID']);
        }
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function refundPolicy(int $companyId, string $date, string $currency): array
    {
        try {
            return $this->policies->resolve($companyId, 'CUSTOMER_REFUND', null, $date, $currency);
        } catch (ValidationException $exception) {
            $first = collect($exception->errors())->flatten()->first();
            throw ValidationException::withMessages(['accounting_policy' => $first === 'ACCOUNTING_POSTING_POLICY_MISSING' ? 'CUSTOMER_REFUND_POSTING_POLICY_MISSING' : 'CUSTOMER_REFUND_POSTING_POLICY_INVALID']);
        }
    }

    /** @param array{lines:list<array{line_role:string,entry_side:string,account_code:string}>} $policy */
    private function policyAccount(array $policy, string $role, string $side): string
    {
        foreach ($policy['lines'] as $line) {
            if ($line['line_role'] === $role && $line['entry_side'] === $side) {
                return $line['account_code'];
            }
        }
        throw ValidationException::withMessages(['accounting_policy' => 'CUSTOMER_REFUND_POSTING_POLICY_INVALID']);
    }

    private function creditNote(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('customer_credit_notes')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function refund(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('customer_refunds')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function assertSchema(): void
    {
        if (! Schema::hasTable('customer_refunds') || ! Schema::hasTable('customer_refund_allocations')) {
            throw ValidationException::withMessages(['schema' => 'SCHEMA_UPGRADE_REQUIRED']);
        }
    }

    /** @param array<string,mixed> $extra */
    private function audit(User $actor, string $event, int $companyId, int $id, array $extra = []): void
    {
        activity('customer-refund')->causedBy($actor)->event($event)->withProperties(['company_id' => $companyId, 'refund_id' => $id, ...$extra])->log('Customer refund '.$event);
    }

    private function positive(mixed $value, string $field): int
    {
        $number = is_numeric($value) ? (int) $value : 0;

        return $number > 0 ? $number : throw ValidationException::withMessages([$field => 'Giá trị phải lớn hơn 0.']);
    }

    private function int(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid customer refund integer: '.$field);
    }

    private function value(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid customer refund text: '.$field);
    }

    private function text(mixed $value, string $field): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages([$field => 'Giá trị bắt buộc.']);
    }
}
