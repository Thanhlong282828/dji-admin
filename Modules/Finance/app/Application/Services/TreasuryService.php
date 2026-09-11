<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class TreasuryService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId): array
    {
        $accounts = $this->db->table('treasury_financial_accounts as f')->join('accounting_accounts as a', 'a.id', '=', 'f.accounting_account_id')->leftJoin('branches as b', 'b.id', '=', 'f.branch_id')->where('f.company_id', $companyId)->select(['f.*', 'a.code as ledger_code', 'b.name as branch_name'])->orderBy('f.code')->get();
        $transactions = $this->db->table('treasury_transactions as t')->join('treasury_financial_accounts as f', 'f.id', '=', 't.financial_account_id')->leftJoin('branches as b', 'b.id', '=', 't.branch_id')->where('t.company_id', $companyId)->select(['t.*', 'f.name as account_name', 'b.name as branch_name'])->orderByDesc('t.occurred_at')->limit(100)->get();
        $sessions = $this->db->table('treasury_reconciliation_sessions as r')->join('treasury_financial_accounts as f', 'f.id', '=', 'r.financial_account_id')->where('r.company_id', $companyId)->select(['r.*', 'f.name as account_name'])->orderByDesc('r.id')->get();

        return ['accounts' => $accounts, 'transactions' => $transactions, 'sessions' => $sessions, 'ledgerAccounts' => $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'status' => 'active'])->whereIn('code', ['1100'])->get(), 'branches' => $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->get()];
    }

    /** @param array<string,mixed> $data */
    public function mutate(string $action, int $companyId, User $actor, array $data): int
    {
        return match ($action) {
            'account.create' => $this->account($companyId, $actor, $data),
            'reconciliation.create' => $this->reconciliation($companyId, $actor, $data),
            'statement.create' => $this->statement($companyId, $data),
            'statement.match' => $this->match($companyId, $data),
            'reconciliation.complete' => $this->complete($companyId, $actor, $data),
            default => throw ValidationException::withMessages(['action' => 'Treasury action không hợp lệ.']),
        };
    }

    public function recordSource(int $companyId, ?int $branchId, string $sourceType, int $sourceId, int $journalId, string $reference, int $amountMinor, string $occurredAt, int $actorId, string $direction = 'outflow'): int
    {
        return $this->db->transaction(function () use ($companyId, $branchId, $sourceType, $sourceId, $journalId, $reference, $amountMinor, $occurredAt, $actorId, $direction): int {
            if (! in_array($direction, ['inflow', 'outflow'], true)) {
                throw ValidationException::withMessages(['direction' => 'Treasury direction không hợp lệ.']);
            }
            $existing = $this->db->table('treasury_transactions')->where(['company_id' => $companyId, 'source_type' => $sourceType, 'source_id' => $sourceId])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $accountId = $this->defaultAccount($companyId, $branchId, $actorId);

            return (int) $this->db->table('treasury_transactions')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'financial_account_id' => $accountId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'accounting_journal_id' => $journalId, 'direction' => $direction, 'reference' => $reference, 'amount_minor' => $amountMinor, 'currency' => 'VND', 'occurred_at' => $occurredAt, 'status' => 'recorded', 'idempotency_key' => implode(':', [$companyId, $sourceType, $sourceId]), 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    /** @param array<string,mixed> $d */
    private function account(int $companyId, User $actor, array $d): int
    {
        $ledger = $this->db->table('accounting_accounts')->where(['id' => $this->integer($d, 'accounting_account_id'), 'company_id' => $companyId, 'status' => 'active'])->firstOrFail();
        unset($ledger);
        $branch = $this->nullableInt($d, 'branch_id');
        if ($branch !== null) {
            $this->db->table('branches')->where(['id' => $branch, 'company_id' => $companyId])->whereNull('deleted_at')->firstOrFail();
        }

        return (int) $this->db->table('treasury_financial_accounts')->insertGetId(['company_id' => $companyId, 'branch_id' => $branch, 'accounting_account_id' => $this->integer($d, 'accounting_account_id'), 'code' => $this->text($d, 'code'), 'name' => $this->text($d, 'name'), 'type' => $this->choice($d, 'type', ['cash', 'bank', 'payment_clearing']), 'currency' => 'VND', 'status' => 'active', 'note' => $d['note'] ?? null, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function reconciliation(int $companyId, User $actor, array $d): int
    {
        $account = $this->db->table('treasury_financial_accounts')->where(['id' => $this->integer($d, 'financial_account_id'), 'company_id' => $companyId, 'status' => 'active'])->firstOrFail();
        unset($account);

        return (int) $this->db->table('treasury_reconciliation_sessions')->insertGetId(['company_id' => $companyId, 'financial_account_id' => $this->integer($d, 'financial_account_id'), 'period_from' => $this->text($d, 'period_from'), 'period_to' => $this->text($d, 'period_to'), 'status' => 'open', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function statement(int $companyId, array $d): int
    {
        $session = $this->db->table('treasury_reconciliation_sessions')->where(['id' => $this->integer($d, 'session_id'), 'company_id' => $companyId, 'status' => 'open'])->firstOrFail();
        unset($session);

        return (int) $this->db->table('treasury_statement_lines')->insertGetId(['company_id' => $companyId, 'reconciliation_session_id' => $this->integer($d, 'session_id'), 'external_reference' => $this->text($d, 'external_reference'), 'occurred_at' => $this->text($d, 'occurred_at'), 'amount_minor' => $this->integer($d, 'amount_minor'), 'status' => 'unmatched', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function match(int $companyId, array $d): int
    {
        $lineId = $this->integer($d, 'line_id');
        $line = $this->db->table('treasury_statement_lines')->where(['id' => $lineId, 'company_id' => $companyId, 'status' => 'unmatched'])->firstOrFail();
        $transactionId = $this->integer($d, 'transaction_id');
        $transaction = $this->db->table('treasury_transactions')->where(['id' => $transactionId, 'company_id' => $companyId])->firstOrFail();
        $transactionData = get_object_vars($transaction);
        $lineData = get_object_vars($line);
        $amount = $this->valueInt($transactionData['amount_minor'] ?? null);
        $signed = $this->valueText($transactionData['direction'] ?? null) === 'outflow' ? -$amount : $amount;
        if ($signed !== $this->valueInt($lineData['amount_minor'] ?? null)) {
            throw ValidationException::withMessages(['amount' => 'Statement và Treasury transaction không khớp số tiền.']);
        }
        $this->db->table('treasury_statement_lines')->where('id', $lineId)->update(['treasury_transaction_id' => $transactionId, 'status' => 'matched', 'updated_at' => now()]);

        return $lineId;
    }

    /** @param array<string,mixed> $d */
    private function complete(int $companyId, User $actor, array $d): int
    {
        $id = $this->integer($d, 'session_id');

        return $this->db->transaction(function () use ($companyId, $actor, $id): int {
            $session = $this->db->table('treasury_reconciliation_sessions')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($session->status === 'reconciled') {
                return $id;
            }
            if ($this->db->table('treasury_statement_lines')->where('reconciliation_session_id', $id)->where('status', 'unmatched')->exists()) {
                throw ValidationException::withMessages(['reconciliation' => 'Còn statement line chưa match.']);
            }
            $this->db->table('treasury_statement_lines')->where('reconciliation_session_id', $id)->where('status', 'matched')->update(['status' => 'reconciled', 'updated_at' => now()]);
            $this->db->table('treasury_transactions')->whereIn('id', $this->db->table('treasury_statement_lines')->where('reconciliation_session_id', $id)->whereNotNull('treasury_transaction_id')->pluck('treasury_transaction_id'))->update(['status' => 'reconciled', 'updated_at' => now()]);
            $this->db->table('treasury_reconciliation_sessions')->where('id', $id)->update(['status' => 'reconciled', 'reconciled_by' => $actor->id, 'reconciled_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function defaultAccount(int $companyId, ?int $branchId, int $actorId): int
    {
        $id = $this->db->table('treasury_financial_accounts')->where(['company_id' => $companyId, 'branch_id' => $branchId, 'type' => 'payment_clearing', 'status' => 'active'])->value('id');
        if (is_numeric($id)) {
            return (int) $id;
        }
        $ledger = $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'code' => '1100'])->value('id');
        if (! is_numeric($ledger)) {
            throw ValidationException::withMessages(['treasury' => 'ACCOUNTING_PAYMENT_CLEARING_REQUIRED']);
        }
        $this->db->table('treasury_financial_accounts')->insertOrIgnore(['company_id' => $companyId, 'branch_id' => $branchId, 'accounting_account_id' => (int) $ledger, 'code' => 'CLEARING-'.($branchId ?? 'HQ'), 'name' => 'Payment Clearing', 'type' => 'payment_clearing', 'currency' => 'VND', 'status' => 'active', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);

        return $this->valueInt($this->db->table('treasury_financial_accounts')->where(['company_id' => $companyId, 'branch_id' => $branchId, 'type' => 'payment_clearing'])->value('id'));
    }

    /** @param array<string,mixed> $d */
    private function integer(array $d, string $key): int
    {
        $v = $d[$key] ?? null;

        return is_int($v) ? $v : (is_string($v) && preg_match('/^-?\d+$/', $v) === 1 ? (int) $v : throw ValidationException::withMessages([$key => 'Giá trị số không hợp lệ.']));
    }

    /** @param array<string,mixed> $d */
    private function nullableInt(array $d, string $key): ?int
    {
        return ($d[$key] ?? null) === null || $d[$key] === '' ? null : $this->integer($d, $key);
    }

    /** @param array<string,mixed> $d */
    private function text(array $d, string $key): string
    {
        $v = $d[$key] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : throw ValidationException::withMessages([$key => 'Giá trị bắt buộc.']);
    }

    /**
     * @param  array<string,mixed>  $d
     * @param  list<string>  $allowed
     */
    private function choice(array $d, string $key, array $allowed): string
    {
        $v = $this->text($d, $key);

        return in_array($v, $allowed, true) ? $v : throw ValidationException::withMessages([$key => 'Giá trị không hỗ trợ.']);
    }

    private function valueInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : throw new \RuntimeException('Invalid treasury integer.'));
    }

    private function valueText(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid treasury text.');
    }
}
