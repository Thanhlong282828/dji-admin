<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final readonly class TreasuryOpeningBalanceService
{
    public function __construct(
        private ConnectionInterface $db,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
        private TreasuryCashPositionReadService $cashPosition,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        try {
            return $this->db->transaction(function () use ($companyId, $actor, $data): int {
                $account = $this->account($companyId, $this->integer($data, 'treasury_financial_account_id'));
                $currency = strtoupper($this->text($data, 'currency'));
                if ($currency !== $this->textObject($account, 'currency')) {
                    throw ValidationException::withMessages(['currency' => 'TREASURY_OPENING_BALANCE_CURRENCY_MISMATCH']);
                }
                $key = $this->text($data, 'idempotency_key');
                $existing = $this->db->table('treasury_opening_balances')->where('idempotency_key', $key)->lockForUpdate()->value('id');
                if (is_numeric($existing)) {
                    return (int) $existing;
                }
                $effectiveDate = $this->text($data, 'effective_date');
                if ($effectiveDate < $this->textObject($account, 'effective_from') || (($account->effective_to ?? null) !== null && $effectiveDate > $this->textObject($account, 'effective_to'))) {
                    throw ValidationException::withMessages(['effective_date' => 'TREASURY_OPENING_BALANCE_ACCOUNT_NOT_EFFECTIVE']);
                }
                $supersedes = $this->nullableInteger($data, 'supersedes_opening_balance_id');
                if ($supersedes !== null) {
                    $previous = $this->db->table('treasury_opening_balances')->where(['id' => $supersedes, 'company_id' => $companyId, 'treasury_financial_account_id' => $this->integer($data, 'treasury_financial_account_id'), 'currency' => $currency, 'status' => 'posted'])->lockForUpdate()->firstOrFail();
                    if ($effectiveDate <= $this->textObject($previous, 'effective_date')) {
                        throw ValidationException::withMessages(['effective_date' => 'TREASURY_OPENING_BALANCE_CORRECTION_DATE_INVALID']);
                    }
                }

                return (int) $this->db->table('treasury_opening_balances')->insertGetId([
                    'company_id' => $companyId,
                    'treasury_financial_account_id' => $this->integer($data, 'treasury_financial_account_id'),
                    'supersedes_opening_balance_id' => $supersedes,
                    'currency' => $currency,
                    'effective_date' => $effectiveDate,
                    'opening_amount_minor' => $this->nonNegative($data, 'opening_amount_minor'),
                    'reference' => $this->text($data, 'reference'),
                    'source_note' => $this->nullableText($data, 'source_note'),
                    'status' => 'draft',
                    'idempotency_key' => $key,
                    'created_by' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $canonical = $this->db->table('treasury_opening_balances')
                ->where('company_id', $companyId)
                ->where(function (Builder $query) use ($data): void {
                    $query->where('idempotency_key', $this->text($data, 'idempotency_key'))
                        ->orWhere(function (Builder $basis) use ($data): void {
                            $basis->where('treasury_financial_account_id', $this->integer($data, 'treasury_financial_account_id'))
                                ->where('currency', strtoupper($this->text($data, 'currency')))
                                ->where('effective_date', $this->text($data, 'effective_date'));
                        });
                })
                ->value('id');
            if (is_numeric($canonical)) {
                return (int) $canonical;
            }

            throw ValidationException::withMessages(['opening_balance' => 'TREASURY_OPENING_BALANCE_CONFLICT']);
        }
    }

    public function submit(int $companyId, int $id, User $actor): int
    {
        return $this->transition($companyId, $id, $actor, 'draft', 'submitted');
    }

    public function approve(int $companyId, int $id, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $row = $this->balance($companyId, $id);
            if ($this->textObject($row, 'status') === 'approved') {
                return $id;
            }
            if ($this->textObject($row, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'TREASURY_OPENING_BALANCE_TRANSITION_INVALID']);
            }
            if ($this->integerObject($row, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('treasury_opening_balances')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    public function post(int $companyId, int $id, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $row = $this->balance($companyId, $id);
            if ($this->textObject($row, 'status') === 'posted') {
                return $id;
            }
            if ($this->textObject($row, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'TREASURY_OPENING_BALANCE_TRANSITION_INVALID']);
            }
            $account = $this->account($companyId, $this->integerObject($row, 'treasury_financial_account_id'));
            $date = $this->textObject($row, 'effective_date');
            $currency = $this->textObject($row, 'currency');
            $policy = $this->policies->resolve($companyId, 'TREASURY_OPENING_BALANCE', $this->textObject($account, 'type'), $date, $currency);
            if (count($policy['lines']) !== 2) {
                throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_OPENING_BALANCE_POLICY_LINES_INVALID']);
            }
            $amount = $this->integerObject($row, 'opening_amount_minor');
            $postingAdjustment = $amount;
            $supersedes = $this->nullableIntegerObject($row, 'supersedes_opening_balance_id');
            if ($supersedes !== null) {
                $previous = $this->db->table('treasury_opening_balances')->where(['id' => $supersedes, 'company_id' => $companyId, 'status' => 'posted'])->firstOrFail();
                $priorDate = CarbonImmutable::parse($date)->subDay()->toDateString();
                $priorResult = $this->cashPosition->read($companyId, $priorDate, $this->nullableIntegerObject($account, 'branch_id'));
                $priorRows = $priorResult['accounts'] ?? null;
                $prior = null;
                if (is_array($priorRows)) {
                    foreach ($priorRows as $candidate) {
                        if (is_array($candidate) && ($candidate['account_id'] ?? null) === $this->integerObject($account, 'id')) {
                            $prior = $candidate;
                            break;
                        }
                    }
                }
                if (! is_array($prior) || ($prior['status'] ?? null) !== 'SUPPORTED' || ! is_int($prior['balance_minor'] ?? null) || $this->integerObject($previous, 'treasury_financial_account_id') !== $this->integerObject($row, 'treasury_financial_account_id')) {
                    throw ValidationException::withMessages(['supersedes_opening_balance_id' => 'TREASURY_OPENING_BALANCE_CORRECTION_EVIDENCE_INVALID']);
                }
                $postingAdjustment = $amount - $prior['balance_minor'];
            }
            $journalAmount = abs($postingAdjustment);
            $lines = [];
            foreach ($policy['lines'] as $line) {
                if ($line['line_role'] === 'CASH' && ($line['entry_side'] !== 'debit' || $line['account_id'] !== $this->integerObject($account, 'accounting_account_id'))) {
                    throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_OPENING_BALANCE_CASH_MAPPING_INVALID']);
                }
                if ($line['line_role'] === 'OPENING_EQUITY' && $line['entry_side'] !== 'credit') {
                    throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_OPENING_BALANCE_OFFSET_INVALID']);
                }
                if (! in_array($line['line_role'], ['CASH', 'OPENING_EQUITY'], true)) {
                    throw ValidationException::withMessages(['accounting_policy' => 'TREASURY_OPENING_BALANCE_POLICY_ROLE_INVALID']);
                }
                $side = $postingAdjustment < 0 ? ($line['entry_side'] === 'debit' ? 'credit' : 'debit') : $line['entry_side'];
                $lines[] = ['account_code' => $line['account_code'], 'debit' => $side === 'debit' ? $journalAmount : 0, 'credit' => $side === 'credit' ? $journalAmount : 0, 'description' => 'Treasury opening balance '.$this->textObject($row, 'reference')];
            }
            $journalId = $this->accounting->postTreasuryOpeningBalance($companyId, $this->nullableIntegerObject($account, 'branch_id'), $id, $date, $currency, $lines, $actor->id, $policy['policy_id'], $policy['version']);
            $this->db->table('treasury_opening_balances')->where('id', $id)->update(['status' => 'posted', 'posting_adjustment_minor' => $postingAdjustment, 'accounting_posting_policy_id' => $policy['policy_id'], 'accounting_posting_policy_version' => $policy['version'], 'accounting_journal_id' => $journalId, 'posted_by' => $actor->id, 'posted_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function transition(int $companyId, int $id, User $actor, string $from, string $to): int
    {
        return $this->db->transaction(function () use ($companyId, $id, $actor, $from, $to): int {
            $row = $this->balance($companyId, $id);
            if ($this->textObject($row, 'status') === $to) {
                return $id;
            }
            if ($this->textObject($row, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'TREASURY_OPENING_BALANCE_TRANSITION_INVALID']);
            }
            $this->db->table('treasury_opening_balances')->where('id', $id)->update(['status' => $to, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function balance(int $companyId, int $id): object
    {
        return $this->db->table('treasury_opening_balances')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    private function account(int $companyId, int $id): object
    {
        return $this->db->table('treasury_financial_accounts as financial')->join('accounting_accounts as ledger', 'ledger.id', '=', 'financial.accounting_account_id')->where(['financial.id' => $id, 'financial.company_id' => $companyId, 'financial.status' => 'active', 'ledger.company_id' => $companyId, 'ledger.status' => 'active', 'ledger.allow_posting' => true])->firstOrFail(['financial.id', 'financial.branch_id', 'financial.accounting_account_id', 'financial.type', 'financial.currency', 'ledger.effective_from', 'ledger.effective_to']);
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : throw ValidationException::withMessages([$key => 'integer required']);
    }

    /** @param array<string,mixed> $data */
    private function nullableInteger(array $data, string $key): ?int
    {
        return ($data[$key] ?? null) === null ? null : $this->integer($data, $key);
    }

    /** @param array<string,mixed> $data */
    private function nonNegative(array $data, string $key): int
    {
        $value = $this->integer($data, $key);

        return $value >= 0 ? $value : throw ValidationException::withMessages([$key => 'non-negative required']);
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

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid opening balance integer.'));
    }

    private function nullableIntegerObject(object $row, string $key): ?int
    {
        $value = $row->{$key} ?? null;

        return $value === null ? null : $this->integerObject($row, $key);
    }

    private function textObject(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid opening balance text.');
    }
}
