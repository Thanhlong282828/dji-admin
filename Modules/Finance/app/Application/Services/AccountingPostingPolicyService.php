<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

final readonly class AccountingPostingPolicyService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $this->lockCompany($companyId);
            $key = $this->text($data, 'policy_key');
            $version = $this->positive($data, 'version');
            $existing = $this->db->table('accounting_posting_policies')->where(['company_id' => $companyId, 'policy_key' => $key, 'version' => $version])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $from = $this->date($data, 'effective_from');
            $to = $this->nullableDate($data, 'effective_to');
            if ($to !== null && $to->lessThan($from)) {
                throw ValidationException::withMessages(['effective_to' => 'ACCOUNTING_POSTING_POLICY_DATE_RANGE_INVALID']);
            }
            $currency = strtoupper($this->text($data, 'currency'));
            if (strlen($currency) !== 3) {
                throw ValidationException::withMessages(['currency' => 'ACCOUNTING_POSTING_POLICY_CURRENCY_INVALID']);
            }
            $lines = $data['lines'] ?? null;
            if (! is_array($lines) || count($lines) < 2) {
                throw ValidationException::withMessages(['lines' => 'ACCOUNTING_POSTING_POLICY_LINES_REQUIRED']);
            }
            $id = (int) $this->db->table('accounting_posting_policies')->insertGetId([
                'company_id' => $companyId, 'policy_key' => $key, 'source_type' => $this->text($data, 'source_type'),
                'business_event' => $this->text($data, 'business_event'), 'classification' => $this->nullableText($data, 'classification'),
                'version' => $version, 'currency' => $currency, 'status' => 'draft', 'effective_from' => $from->toDateString(),
                'effective_to' => $to?->toDateString(), 'note' => $this->nullableText($data, 'note'), 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_values($lines) as $index => $line) {
                if (! is_array($line)) {
                    throw ValidationException::withMessages(['lines' => 'ACCOUNTING_POSTING_POLICY_LINE_INVALID']);
                }
                $role = strtoupper($this->arrayText($line, 'line_role'));
                $side = strtolower($this->arrayText($line, 'entry_side'));
                if (! in_array($side, ['debit', 'credit'], true)) {
                    throw ValidationException::withMessages(['entry_side' => 'ACCOUNTING_POSTING_POLICY_SIDE_INVALID']);
                }
                $account = $this->account($companyId, $this->arrayPositive($line, 'account_id'), $role, $from, $to);
                $event = $this->text($data, 'business_event');
                if (str_starts_with($event, 'SERVICE_') && $role === 'ACCRUAL' && $this->accountCode($account) === '2150') {
                    throw ValidationException::withMessages(['account_id' => 'GOODS_GRNI_FORBIDDEN_FOR_SERVICE']);
                }
                $this->db->table('accounting_posting_policy_lines')->insert([
                    'accounting_posting_policy_id' => $id, 'sequence' => $index + 1, 'entry_side' => $side, 'line_role' => $role,
                    'account_id' => $this->objectInteger($account, 'id'), 'requires_cost_center' => (bool) ($line['requires_cost_center'] ?? false),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $id;
        }, 3);
    }

    public function submit(int $companyId, int $policyId, User $actor): int
    {
        return $this->transition($companyId, $policyId, $actor, 'draft', 'submitted');
    }

    public function approve(int $companyId, int $policyId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $actor): int {
            $this->lockCompany($companyId);
            $policy = $this->policy($companyId, $policyId);
            if ($this->objectText($policy, 'status') === 'approved') {
                return $policyId;
            }
            if ($this->objectText($policy, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'ACCOUNTING_POSTING_POLICY_TRANSITION_INVALID']);
            }
            if ($this->objectInteger($policy, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $overlap = $this->db->table('accounting_posting_policies')->where('company_id', $companyId)->where('id', '<>', $policyId)
                ->where(['business_event' => $this->objectText($policy, 'business_event'), 'currency' => $this->objectText($policy, 'currency'), 'status' => 'approved'])
                ->where('classification', $this->objectNullableText($policy, 'classification'))
                ->whereDate('effective_from', '<=', $this->objectNullableText($policy, 'effective_to') ?? '9999-12-31')
                ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->objectText($policy, 'effective_from')))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'ACCOUNTING_POSTING_POLICY_OVERLAP']);
            }
            $this->validateStoredLines($companyId, $policy);
            $this->db->table('accounting_posting_policies')->where('id', $policyId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $policyId;
        }, 3);
    }

    private function transition(int $companyId, int $policyId, User $actor, string $from, string $to): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $actor, $from, $to): int {
            $policy = $this->policy($companyId, $policyId);
            if ($this->objectText($policy, 'status') === $to) {
                return $policyId;
            }
            if ($this->objectText($policy, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'ACCOUNTING_POSTING_POLICY_TRANSITION_INVALID']);
            }
            $this->db->table('accounting_posting_policies')->where('id', $policyId)->update(['status' => $to, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);

            return $policyId;
        }, 3);
    }

    private function validateStoredLines(int $companyId, object $policy): void
    {
        $from = CarbonImmutable::parse($this->objectText($policy, 'effective_from'));
        $toText = $this->objectNullableText($policy, 'effective_to');
        $to = $toText === null ? null : CarbonImmutable::parse($toText);
        $lines = $this->db->table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $this->objectInteger($policy, 'id'))->get();
        foreach ($lines as $line) {
            $account = $this->account($companyId, $this->objectInteger($line, 'account_id'), $this->objectText($line, 'line_role'), $from, $to);
            if (str_starts_with($this->objectText($policy, 'business_event'), 'SERVICE_') && $this->objectText($line, 'line_role') === 'ACCRUAL' && $this->accountCode($account) === '2150') {
                throw ValidationException::withMessages(['account_id' => 'GOODS_GRNI_FORBIDDEN_FOR_SERVICE']);
            }
        }
    }

    private function account(int $companyId, int $accountId, string $role, CarbonImmutable $from, ?CarbonImmutable $to): object
    {
        $account = $this->db->table('accounting_accounts')->where(['id' => $accountId, 'company_id' => $companyId, 'status' => 'active', 'allow_posting' => true])->first();
        if ($account === null) {
            throw ValidationException::withMessages(['account_id' => 'ACCOUNTING_POSTING_POLICY_ACCOUNT_INVALID']);
        }
        $expected = match ($role) {
            'EXPENSE' => ['expense'], 'ACCRUAL', 'AP' => ['liability'], 'CASH', 'INVENTORY', 'ASSET' => ['asset'], 'GRNI' => ['liability'],
            default => throw ValidationException::withMessages(['line_role' => 'ACCOUNTING_POSTING_POLICY_ROLE_INVALID']),
        };
        if (! in_array($this->objectText($account, 'account_type'), $expected, true)) {
            throw ValidationException::withMessages(['account_id' => 'ACCOUNTING_POSTING_POLICY_ACCOUNT_TYPE_INVALID']);
        }
        $accountFrom = CarbonImmutable::parse($this->objectText($account, 'effective_from'));
        $accountToText = $this->objectNullableText($account, 'effective_to');
        $accountTo = $accountToText === null ? null : CarbonImmutable::parse($accountToText);
        if ($accountFrom->greaterThan($from) || ($accountTo !== null && ($to === null || $accountTo->lessThan($to)))) {
            throw ValidationException::withMessages(['account_id' => 'ACCOUNTING_POSTING_POLICY_ACCOUNT_NOT_EFFECTIVE']);
        }

        return $account;
    }

    private function lockCompany(int $companyId): void
    {
        $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail(['id']);
    }

    private function policy(int $companyId, int $policyId): object
    {
        return $this->db->table('accounting_posting_policies')->where(['id' => $policyId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
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

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : throw ValidationException::withMessages([$key => 'positive integer required']);
    }

    /** @param array<string,mixed> $data */
    private function date(array $data, string $key): CarbonImmutable
    {
        return CarbonImmutable::parse($this->text($data, $key));
    }

    /** @param array<string,mixed> $data */
    private function nullableDate(array $data, string $key): ?CarbonImmutable
    {
        $value = $this->nullableText($data, $key);

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    /** @param array<mixed,mixed> $data */
    private function arrayText(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw ValidationException::withMessages([$key => 'required']);
    }

    /** @param array<mixed,mixed> $data */
    private function arrayPositive(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : throw ValidationException::withMessages([$key => 'positive integer required']);
    }

    private function objectInteger(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid policy integer.'));
    }

    private function objectText(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid policy text.');
    }

    private function objectNullableText(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function accountCode(object $account): string
    {
        return $this->objectText($account, 'code');
    }
}
