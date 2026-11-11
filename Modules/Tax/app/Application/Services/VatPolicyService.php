<?php

namespace Modules\Tax\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class VatPolicyService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $this->lockCompany($companyId);
            $key = $this->text($data, 'policy_key');
            $maximumVersion = $this->db->table('vat_policies')->where(['company_id' => $companyId, 'policy_key' => $key])->max('version');
            $version = (is_int($maximumVersion) || (is_string($maximumVersion) && ctype_digit($maximumVersion)) ? (int) $maximumVersion : 0) + 1;
            $values = $this->validatedValues($companyId, $data);

            return (int) $this->db->table('vat_policies')->insertGetId($values + [
                'company_id' => $companyId, 'policy_key' => $key, 'version' => $version, 'status' => 'draft',
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function updateDraft(int $companyId, int $policyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $actor, $data): int {
            $policy = $this->policy($companyId, $policyId);
            $this->authorizeCreator($policy, $actor);
            if ($this->policyText($policy, 'status') !== 'draft') {
                throw ValidationException::withMessages(['status' => 'VAT_POLICY_APPROVED_IMMUTABLE']);
            }
            $this->db->table('vat_policies')->where('id', $policyId)->update($this->validatedValues($companyId, $data) + ['updated_at' => now()]);

            return $policyId;
        }, 3);
    }

    public function approve(int $companyId, int $policyId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $actor): int {
            $policy = $this->policy($companyId, $policyId);
            if ($this->policyText($policy, 'status') === 'submitted') {
                return $policyId;
            }
            if ($this->policyText($policy, 'status') !== 'draft') {
                throw ValidationException::withMessages(['status' => 'VAT_POLICY_APPROVAL_STATE_INVALID']);
            }
            if ($this->policyInt($policy, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('vat_policies')->where('id', $policyId)->update(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);

            return $policyId;
        }, 3);
    }

    public function activate(int $companyId, int $policyId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $actor): int {
            $policy = $this->policy($companyId, $policyId);
            if ($this->policyText($policy, 'status') === 'approved') {
                return $policyId;
            }
            if ($this->policyText($policy, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'VAT_POLICY_ACTIVATION_STATE_INVALID']);
            }
            $effectiveTo = $policy->effective_to ?? null;
            $overlap = $this->db->table('vat_policies')->where('company_id', $companyId)->where('id', '<>', $policyId)->where(['tax_category_id' => $this->policyInt($policy, 'tax_category_id'), 'currency' => $this->policyText($policy, 'currency'), 'status' => 'approved'])->whereDate('effective_from', '<=', is_string($effectiveTo) ? $effectiveTo : '9999-12-31')->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->policyText($policy, 'effective_from')))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'VAT_POLICY_EFFECTIVE_PERIOD_OVERLAP']);
            }
            $this->db->table('vat_policies')->where('id', $policyId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $policyId;
        }, 3);
    }

    public function expire(int $companyId, int $policyId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId): int {
            $policy = $this->policy($companyId, $policyId);
            if ($this->policyText($policy, 'status') === 'retired') {
                return $policyId;
            }
            if ($this->policyText($policy, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'VAT_POLICY_EXPIRY_STATE_INVALID']);
            }
            $this->db->table('vat_policies')->where('id', $policyId)->update(['status' => 'retired', 'updated_at' => now()]);

            return $policyId;
        }, 3);
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function validatedValues(int $companyId, array $data): array
    {
        $from = CarbonImmutable::parse($this->text($data, 'effective_from'))->toDateString();
        $toValue = $data['effective_to'] ?? null;
        $to = is_string($toValue) && $toValue !== '' ? CarbonImmutable::parse($toValue)->toDateString() : null;
        if ($to !== null && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'VAT_POLICY_DATE_RANGE_INVALID']);
        }
        $categoryId = $this->integer($data, 'tax_category_id');
        $taxAccountId = $this->integer($data, 'tax_account_id');
        $postingPolicyId = $this->integer($data, 'accounting_posting_policy_id');
        abort_unless($this->db->table('tax_categories')->where(['id' => $categoryId, 'company_id' => $companyId, 'status' => 'active'])->exists(), 404);
        abort_unless($this->db->table('accounting_accounts')->where(['id' => $taxAccountId, 'company_id' => $companyId, 'status' => 'active', 'allow_posting' => true])->exists(), 404);
        abort_unless($this->db->table('accounting_posting_policies')->where(['id' => $postingPolicyId, 'company_id' => $companyId, 'status' => 'approved'])->exists(), 404);
        $countryCode = strtoupper($this->text($data, 'country_code'));
        $currency = strtoupper($this->text($data, 'currency'));
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw ValidationException::withMessages(['country_code' => 'VAT_POLICY_COUNTRY_INVALID']);
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency' => 'VAT_POLICY_CURRENCY_INVALID']);
        }
        $mapping = $this->db->table('accounting_posting_policy_lines')
            ->where('accounting_posting_policy_id', $postingPolicyId);
        if ((clone $mapping)->count() !== 3 || (clone $mapping)->where('account_id', $taxAccountId)->count() !== 1) {
            throw ValidationException::withMessages(['tax_account_id' => 'TAX_MAPPING_REQUIRED']);
        }
        $rate = $this->integer($data, 'rate_basis_points');
        if ($rate > 10_000) {
            throw ValidationException::withMessages(['rate_basis_points' => 'VAT_POLICY_RATE_INVALID']);
        }

        return ['tax_category_id' => $categoryId, 'country_code' => $countryCode, 'currency' => $currency, 'rate_basis_points' => $rate, 'effective_from' => $from, 'effective_to' => $to, 'tax_account_id' => $taxAccountId, 'accounting_posting_policy_id' => $postingPolicyId];
    }

    private function policy(int $companyId, int $policyId): stdClass
    {
        return $this->db->table('vat_policies')->where(['id' => $policyId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    private function lockCompany(int $companyId): void
    {
        $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail(['id']);
    }

    private function authorizeCreator(stdClass $policy, User $actor): void
    {
        if ($this->policyInt($policy, 'created_by') !== $actor->id) {
            throw ValidationException::withMessages(['policy' => 'VAT_POLICY_DRAFT_OWNER_REQUIRED']);
        }
    }

    private function policyInt(stdClass $policy, string $property): int
    {
        $value = $policy->{$property} ?? null;

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : throw new \LogicException('Invalid VAT policy integer.');
    }

    private function policyText(stdClass $policy, string $property): string
    {
        $value = $policy->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \LogicException('Invalid VAT policy text.');
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw ValidationException::withMessages([$key => 'required']);
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : throw ValidationException::withMessages([$key => 'positive integer required']);
    }
}
