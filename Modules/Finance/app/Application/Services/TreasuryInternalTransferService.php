<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final readonly class TreasuryInternalTransferService
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
        $key = $this->text($data, 'idempotency_key');
        try {
            return $this->db->transaction(function () use ($companyId, $actor, $data, $key): int {
                $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail(['id']);
                $existing = $this->db->table('treasury_internal_transfers')->where(['company_id' => $companyId, 'idempotency_key' => $key])->value('id');
                if (is_numeric($existing)) {
                    return (int) $existing;
                }
                $sourceId = $this->integer($data, 'source_treasury_account_id');
                $destinationId = $this->integer($data, 'destination_treasury_account_id');
                if ($sourceId === $destinationId) {
                    throw ValidationException::withMessages(['destination_treasury_account_id' => 'TREASURY_INTERNAL_TRANSFER_SAME_ACCOUNT']);
                }
                $occurredAt = $this->text($data, 'occurred_at');
                $source = $this->account($companyId, $sourceId, $occurredAt);
                $destination = $this->account($companyId, $destinationId, $occurredAt);
                $currency = strtoupper($this->text($data, 'currency'));
                if ($this->textObject($source, 'currency') !== $currency || $this->textObject($destination, 'currency') !== $currency) {
                    throw ValidationException::withMessages(['currency' => 'TREASURY_INTERNAL_TRANSFER_CURRENCY_MISMATCH']);
                }
                if ($this->nullableIntegerObject($source, 'branch_id') !== $this->nullableIntegerObject($destination, 'branch_id')) {
                    throw ValidationException::withMessages(['destination_treasury_account_id' => 'TREASURY_INTERNAL_TRANSFER_BRANCH_UNSUPPORTED']);
                }
                $amount = $this->positive($data, 'amount_minor');
                $id = (int) $this->db->table('treasury_internal_transfers')->insertGetId([
                    'company_id' => $companyId, 'source_treasury_account_id' => $sourceId, 'destination_treasury_account_id' => $destinationId,
                    'currency' => $currency, 'amount_minor' => $amount, 'occurred_at' => $occurredAt, 'reference' => $this->text($data, 'reference'),
                    'reason' => $this->nullableText($data, 'reason'), 'status' => 'draft', 'idempotency_key' => $key, 'created_by' => $actor->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ([['SOURCE_OUT', 'outflow', $sourceId], ['DESTINATION_IN', 'inflow', $destinationId]] as [$role, $direction, $accountId]) {
                    $this->db->table('treasury_internal_transfer_legs')->insert([
                        'treasury_internal_transfer_id' => $id, 'treasury_financial_account_id' => $accountId, 'leg_role' => $role,
                        'direction' => $direction, 'amount_minor' => $amount, 'currency' => $currency, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return $id;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $id = $this->db->table('treasury_internal_transfers')->where(['company_id' => $companyId, 'idempotency_key' => $key])->value('id');
            if (is_numeric($id)) {
                return (int) $id;
            }
            throw ValidationException::withMessages(['transfer' => 'TREASURY_INTERNAL_TRANSFER_CONFLICT']);
        }
    }

    public function submit(int $companyId, int $id, User $actor): int
    {
        return $this->transition($companyId, $id, $actor, 'draft', 'submitted');
    }

    /** @param array<string,mixed> $data */
    public function reverse(int $companyId, int $originalId, User $actor, array $data): int
    {
        if (! Schema::hasColumn('treasury_internal_transfers', 'reversal_of_transfer_id')) {
            throw ValidationException::withMessages(['schema' => 'SCHEMA_UPGRADE_REQUIRED']);
        }
        $key = $this->text($data, 'idempotency_key');
        try {
            return $this->db->transaction(function () use ($companyId, $originalId, $actor, $data, $key): int {
                $original = $this->transfer($companyId, $originalId);
                if ($this->textObject($original, 'status') !== 'posted') {
                    throw ValidationException::withMessages(['transfer' => 'TREASURY_INTERNAL_TRANSFER_REVERSAL_REQUIRES_POSTED']);
                }
                if ($this->nullableIntegerObject($original, 'reversal_of_transfer_id') !== null) {
                    throw ValidationException::withMessages(['transfer' => 'TREASURY_INTERNAL_TRANSFER_REVERSAL_OF_REVERSAL']);
                }
                $existing = $this->db->table('treasury_internal_transfers')->where('reversal_of_transfer_id', $originalId)->lockForUpdate()->value('id');
                if (is_numeric($existing)) {
                    return (int) $existing;
                }
                $sameKey = $this->db->table('treasury_internal_transfers')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->first();
                if ($sameKey !== null) {
                    if ($this->nullableIntegerObject($sameKey, 'reversal_of_transfer_id') !== $originalId) {
                        throw ValidationException::withMessages(['idempotency_key' => 'TREASURY_INTERNAL_TRANSFER_KEY_CONFLICT']);
                    }

                    return $this->integerObject($sameKey, 'id');
                }
                $reason = $this->text($data, 'reason');
                $occurredAt = $this->text($data, 'occurred_at');
                $sourceId = $this->integerObject($original, 'destination_treasury_account_id');
                $destinationId = $this->integerObject($original, 'source_treasury_account_id');
                $source = $this->account($companyId, $sourceId, $occurredAt);
                $destination = $this->account($companyId, $destinationId, $occurredAt);
                $currency = $this->textObject($original, 'currency');
                if ($this->textObject($source, 'currency') !== $currency || $this->textObject($destination, 'currency') !== $currency || $this->nullableIntegerObject($source, 'branch_id') !== $this->nullableIntegerObject($destination, 'branch_id')) {
                    throw ValidationException::withMessages(['transfer' => 'TREASURY_INTERNAL_TRANSFER_REVERSAL_ACCOUNT_INVALID']);
                }
                $amount = $this->integerObject($original, 'amount_minor');
                $id = (int) $this->db->table('treasury_internal_transfers')->insertGetId([
                    'reversal_of_transfer_id' => $originalId, 'company_id' => $companyId, 'source_treasury_account_id' => $sourceId, 'destination_treasury_account_id' => $destinationId,
                    'currency' => $currency, 'amount_minor' => $amount, 'occurred_at' => $occurredAt, 'reference' => $this->text($data, 'reference'), 'reason' => $reason,
                    'status' => 'draft', 'idempotency_key' => $key, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ([['SOURCE_OUT', 'outflow', $sourceId], ['DESTINATION_IN', 'inflow', $destinationId]] as [$role, $direction, $accountId]) {
                    $this->db->table('treasury_internal_transfer_legs')->insert(['treasury_internal_transfer_id' => $id, 'treasury_financial_account_id' => $accountId, 'leg_role' => $role, 'direction' => $direction, 'amount_minor' => $amount, 'currency' => $currency, 'created_at' => now(), 'updated_at' => now()]);
                }
                activity('treasury-internal-transfer')->causedBy($actor)->event('reversal-created')->withProperties(['company_id' => $companyId, 'original_transfer_id' => $originalId, 'reversal_transfer_id' => $id])->log('Treasury internal transfer reversal created');

                return $id;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $id = $this->db->table('treasury_internal_transfers')->where(['company_id' => $companyId, 'reversal_of_transfer_id' => $originalId])->value('id');
            if (is_numeric($id)) {
                return (int) $id;
            }
            throw ValidationException::withMessages(['transfer' => 'TREASURY_INTERNAL_TRANSFER_REVERSAL_CONFLICT']);
        }
    }

    public function approve(int $companyId, int $id, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $row = $this->transfer($companyId, $id);
            if ($this->textObject($row, 'status') === 'approved') {
                return $id;
            }
            if ($this->textObject($row, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'TREASURY_INTERNAL_TRANSFER_TRANSITION_INVALID']);
            }
            if ($this->integerObject($row, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('treasury_internal_transfers')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    public function post(int $companyId, int $id, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $row = $this->transfer($companyId, $id);
            if ($this->textObject($row, 'status') === 'posted') {
                return $id;
            }
            if ($this->textObject($row, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'TREASURY_INTERNAL_TRANSFER_TRANSITION_INVALID']);
            }
            $occurredAt = $this->textObject($row, 'occurred_at');
            $date = substr($occurredAt, 0, 10);
            $source = $this->account($companyId, $this->integerObject($row, 'source_treasury_account_id'), $occurredAt);
            $destination = $this->account($companyId, $this->integerObject($row, 'destination_treasury_account_id'), $occurredAt);
            $branchId = $this->nullableIntegerObject($source, 'branch_id');
            if ($branchId !== $this->nullableIntegerObject($destination, 'branch_id')) {
                throw ValidationException::withMessages(['destination_treasury_account_id' => 'TREASURY_INTERNAL_TRANSFER_BRANCH_UNSUPPORTED']);
            }
            $currency = $this->textObject($row, 'currency');
            $reversalOf = $this->nullableIntegerObject($row, 'reversal_of_transfer_id');
            $original = $reversalOf === null ? null : $this->db->table('treasury_internal_transfers')->where(['id' => $reversalOf, 'company_id' => $companyId, 'status' => 'posted'])->lockForUpdate()->firstOrFail();
            $policy = $original === null
                ? $this->policies->resolve($companyId, 'TREASURY_INTERNAL_TRANSFER', null, $date, $currency)
                : ['policy_id' => $this->integerObject($original, 'accounting_posting_policy_id'), 'version' => $this->integerObject($original, 'accounting_posting_policy_version'), 'lines' => [
                    ['line_role' => 'SOURCE_CASH', 'entry_side' => 'credit', 'account_id' => $this->integerObject($source, 'accounting_account_id')],
                    ['line_role' => 'DESTINATION_CASH', 'entry_side' => 'debit', 'account_id' => $this->integerObject($destination, 'accounting_account_id')],
                ]];
            if (count($policy['lines']) !== 2) {
                throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_INTERNAL_TRANSFER_POLICY_LINES_INVALID']);
            }
            $roles = [];
            foreach ($policy['lines'] as $line) {
                $roles[$line['line_role']] = $line;
            }
            $sourcePolicy = $roles['SOURCE_CASH'] ?? null;
            if ($sourcePolicy === null || $sourcePolicy['entry_side'] !== 'credit' || $sourcePolicy['account_id'] !== $this->integerObject($source, 'accounting_account_id')) {
                throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_INTERNAL_TRANSFER_SOURCE_MAPPING_INVALID']);
            }
            $destinationPolicy = $roles['DESTINATION_CASH'] ?? null;
            if ($destinationPolicy === null || $destinationPolicy['entry_side'] !== 'debit' || $destinationPolicy['account_id'] !== $this->integerObject($destination, 'accounting_account_id')) {
                throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_INTERNAL_TRANSFER_DESTINATION_MAPPING_INVALID']);
            }
            if (count($roles) !== 2) {
                throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_INTERNAL_TRANSFER_POLICY_ROLE_INVALID']);
            }
            $amount = $this->integerObject($row, 'amount_minor');
            $journalLines = [
                ['account_code' => $this->textObject($destination, 'ledger_code'), 'debit' => $amount, 'credit' => 0, 'description' => 'Internal transfer '.$this->textObject($row, 'reference')],
                ['account_code' => $this->textObject($source, 'ledger_code'), 'debit' => 0, 'credit' => $amount, 'description' => 'Internal transfer '.$this->textObject($row, 'reference')],
            ];
            $journalId = $original === null
                ? $this->accounting->postTreasuryInternalTransfer($companyId, $branchId, $id, $date, $currency, $journalLines, $actor->id, $policy['policy_id'], $policy['version'])
                : $this->accounting->postTreasuryInternalTransferReversal($companyId, $branchId, $id, $this->integerObject($original, 'accounting_journal_id'), $date, $currency, $journalLines, $actor->id, $policy['policy_id'], $policy['version']);

            $legs = $this->db->table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $id)->lockForUpdate()->get();
            if ($legs->count() !== 2) {
                throw ValidationException::withMessages(['legs' => 'TREASURY_INTERNAL_TRANSFER_LEGS_INVALID']);
            }
            foreach ($legs as $leg) {
                $legId = $this->integerObject($leg, 'id');
                $txId = $this->treasury->recordSourceToAccount($companyId, $branchId, $this->integerObject($leg, 'treasury_financial_account_id'), 'treasury_internal_transfer_leg', $legId, $journalId, $this->textObject($row, 'reference').':'.$this->textObject($leg, 'leg_role'), $amount, $occurredAt, $actor->id, $this->textObject($leg, 'direction'));
                $this->db->table('treasury_internal_transfer_legs')->where('id', $legId)->update(['treasury_transaction_id' => $txId, 'updated_at' => now()]);
            }
            $this->db->table('treasury_internal_transfers')->where('id', $id)->update([
                'status' => 'posted', 'accounting_posting_policy_id' => $policy['policy_id'], 'accounting_posting_policy_version' => $policy['version'],
                'accounting_journal_id' => $journalId, 'posted_by' => $actor->id, 'posted_at' => now(), 'updated_at' => now(),
            ]);

            return $id;
        }, 3);
    }

    /** @return array{schema_status:string,transfers:mixed} */
    public function read(int $companyId): array
    {
        if (! Schema::hasTable('treasury_internal_transfers')) {
            return ['schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'transfers' => collect()];
        }

        $query = $this->db->table('treasury_internal_transfers as transfer')
            ->join('treasury_financial_accounts as source', 'source.id', '=', 'transfer.source_treasury_account_id')
            ->join('treasury_financial_accounts as destination', 'destination.id', '=', 'transfer.destination_treasury_account_id')
            ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'transfer.accounting_journal_id')
            ->where('transfer.company_id', $companyId)->orderByDesc('transfer.id');
        try {
            $transfers = (clone $query)->leftJoin('treasury_internal_transfers as reversal', 'reversal.reversal_of_transfer_id', '=', 'transfer.id')->addSelect('reversal.id as reversed_by_transfer_id')->addSelect(['transfer.*', 'source.code as source_code', 'source.name as source_name', 'destination.code as destination_code', 'destination.name as destination_name', 'journal.journal_number'])->get();
        } catch (QueryException) {
            return ['schema_status' => 'READY', 'reversal_schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'transfers' => $query->addSelect(['transfer.*', 'source.code as source_code', 'source.name as source_name', 'destination.code as destination_code', 'destination.name as destination_name', 'journal.journal_number'])->get()];
        }

        return ['schema_status' => 'READY', 'reversal_schema_status' => 'READY', 'transfers' => $transfers];
    }

    private function transition(int $companyId, int $id, User $actor, string $from, string $to): int
    {
        return $this->db->transaction(function () use ($companyId, $id, $actor, $from, $to): int {
            $row = $this->transfer($companyId, $id);
            if ($this->textObject($row, 'status') === $to) {
                return $id;
            }
            if ($this->textObject($row, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'TREASURY_INTERNAL_TRANSFER_TRANSITION_INVALID']);
            }
            $this->db->table('treasury_internal_transfers')->where('id', $id)->update(['status' => $to, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function transfer(int $companyId, int $id): object
    {
        return $this->db->table('treasury_internal_transfers')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    private function account(int $companyId, int $id, string $occurredAt): object
    {
        $date = substr($occurredAt, 0, 10);
        $row = $this->db->table('treasury_financial_accounts as financial')->join('accounting_accounts as ledger', 'ledger.id', '=', 'financial.accounting_account_id')
            ->where(['financial.id' => $id, 'financial.company_id' => $companyId, 'financial.status' => 'active', 'ledger.company_id' => $companyId, 'ledger.status' => 'active', 'ledger.allow_posting' => true])
            ->whereIn('financial.type', ['cash', 'bank'])
            ->whereDate('ledger.effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('ledger.effective_to')->orWhereDate('ledger.effective_to', '>=', $date))
            ->first(['financial.id', 'financial.branch_id', 'financial.accounting_account_id', 'financial.type', 'financial.currency', 'ledger.code as ledger_code']);
        if ($row === null) {
            throw ValidationException::withMessages(['treasury_account' => 'TREASURY_INTERNAL_TRANSFER_ACCOUNT_INVALID']);
        }

        return $row;
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : throw ValidationException::withMessages([$key => 'integer required']);
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $this->integer($data, $key);

        return $value > 0 ? $value : throw ValidationException::withMessages([$key => 'positive integer required']);
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw ValidationException::withMessages([$key => 'required']);
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integerObject(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid transfer integer.'));
    }

    private function nullableIntegerObject(object $row, string $key): ?int
    {
        return ($row->{$key} ?? null) === null ? null : $this->integerObject($row, $key);
    }

    private function textObject(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid transfer text.');
    }
}
