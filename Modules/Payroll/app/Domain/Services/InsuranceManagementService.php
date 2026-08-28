<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\InsuranceParticipationStatus;
use Modules\Payroll\Domain\Enums\InsuranceType;

final readonly class InsuranceManagementService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $data */
    public function savePolicy(int $companyId, ?int $policyId, array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $data, $actorId): int {
            $from = $this->text($data, 'effective_from');
            $to = $this->nullableText($data, 'effective_to');
            if ($to !== null && $to < $from) {
                throw ValidationException::withMessages(['effective_to' => 'Effective To phải sau Effective From.']);
            }
            $overlap = $this->db->table('insurance_policies')->where('company_id', $companyId)->where('status', 'active')->when($policyId !== null, fn (Builder $q): Builder => $q->where('id', '<>', $policyId))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->when($to !== null, fn (Builder $q): Builder => $q->whereDate('effective_from', '<=', $to))->exists();
            if ($this->text($data, 'status') === 'active' && $overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Active insurance policy effective range bị overlap.']);
            }
            $values = ['company_id' => $companyId, 'code' => strtoupper($this->text($data, 'code')), 'name' => $this->text($data, 'name'), 'version' => $this->integer($data, 'version', 1), 'effective_from' => $from, 'effective_to' => $to, 'status' => $this->text($data, 'status'), 'currency_code' => strtoupper($this->text($data, 'currency_code')), 'jurisdiction' => $this->text($data, 'jurisdiction'), 'source_reference' => $this->nullableText($data, 'source_reference'), 'source_verified_at' => $this->nullableText($data, 'source_verified_at'), 'rounding_rule' => $this->text($data, 'rounding_rule'), 'note' => $this->nullableText($data, 'note'), 'updated_by' => $actorId, 'updated_at' => now()];
            $creating = $policyId === null;
            if ($creating) {
                $values['created_by'] = $actorId;
                $values['created_at'] = now();
                $policyId = (int) $this->db->table('insurance_policies')->insertGetId($values);
            } else {
                $policy = $this->db->table('insurance_policies')->where('company_id', $companyId)->lockForUpdate()->find($policyId);
                if (! is_object($policy)) {
                    abort(404);
                }
                $used = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.insurance_policy_id', $policyId)->whereIn('r.status', ['submitted', 'approved', 'finalized'])->exists();
                if ($used) {
                    throw ValidationException::withMessages(['policy' => 'Policy đã dùng bởi submitted/finalized Payroll Run; hãy tạo version mới.']);
                }
                $this->db->table('insurance_policies')->where('id', $policyId)->update($values);
                $this->db->table('insurance_policy_rates')->where('insurance_policy_id', $policyId)->delete();
            }
            $rates = $data['rates'] ?? [];
            if (! is_array($rates) || $rates === []) {
                throw ValidationException::withMessages(['rates' => 'Policy cần ít nhất một rate.']);
            }
            foreach (array_values($rates) as $order => $rawRate) {
                if (! is_array($rawRate)) {
                    continue;
                }
                $rate = [];
                foreach ($rawRate as $rateKey => $rateValue) {
                    if (is_string($rateKey)) {
                        $rate[$rateKey] = $rateValue;
                    }
                }
                $type = InsuranceType::tryFrom($this->text($rate, 'insurance_type'));
                $employeeRate = $this->integer($rate, 'employee_rate_basis_points');
                $employerRate = $this->integer($rate, 'employer_rate_basis_points');
                $min = $this->nullableInteger($rate, 'minimum_base_minor');
                $max = $this->nullableInteger($rate, 'maximum_base_minor');
                if ($type === null || $employeeRate > 10000 || $employerRate > 10000 || ($min !== null && $max !== null && $max < $min)) {
                    throw ValidationException::withMessages(['rates' => 'Insurance rate/cap/floor không hợp lệ.']);
                }
                $this->db->table('insurance_policy_rates')->insert(['insurance_policy_id' => $policyId, 'insurance_type' => $type->value, 'employee_rate_basis_points' => $employeeRate, 'employer_rate_basis_points' => $employerRate, 'minimum_base_minor' => $min, 'maximum_base_minor' => $max, 'calculation_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
            }
            activity('payroll-insurance')->causedBy($actorId)->event($creating ? 'created' : 'updated')->withProperties(['company_id' => $companyId, 'policy_id' => $policyId, 'effective_from' => $from, 'effective_to' => $to])->log('Insurance policy saved');

            return $policyId;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function saveProfile(int $companyId, int $employeeId, ?int $profileId, array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $employeeId, $profileId, $data, $actorId): int {
            if (! $this->db->table('employees')->where('company_id', $companyId)->where('id', $employeeId)->exists()) {
                abort(404);
            }
            $status = InsuranceParticipationStatus::tryFrom($this->text($data, 'participation_status'));
            $from = $this->text($data, 'effective_from');
            $to = $this->nullableText($data, 'effective_to');
            if ($status === null || ($to !== null && $to < $from)) {
                throw ValidationException::withMessages(['profile' => 'Insurance profile không hợp lệ.']);
            }
            $policyId = $this->nullableInteger($data, 'insurance_policy_id');
            if ($policyId !== null && ! $this->db->table('insurance_policies')->where('company_id', $companyId)->where('id', $policyId)->exists()) {
                abort(404);
            }
            $overlap = $this->db->table('employee_insurance_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->when($profileId !== null, fn (Builder $q): Builder => $q->where('id', '<>', $profileId))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->when($to !== null, fn (Builder $q): Builder => $q->whereDate('effective_from', '<=', $to))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Employee insurance profile effective range bị overlap.']);
            }
            $values = ['company_id' => $companyId, 'employee_id' => $employeeId, 'insurance_policy_id' => $policyId, 'participation_status' => $status->value, 'effective_from' => $from, 'effective_to' => $to, 'insurance_number' => $this->nullableText($data, 'insurance_number'), 'contribution_base_override_minor' => $this->nullableInteger($data, 'contribution_base_override_minor'), 'currency_code' => $this->nullableText($data, 'currency_code'), 'exemption_reason' => $this->nullableText($data, 'exemption_reason'), 'updated_by' => $actorId, 'updated_at' => now()];
            if ($profileId === null) {
                $values['created_by'] = $actorId;
                $values['created_at'] = now();
                $profileId = (int) $this->db->table('employee_insurance_profiles')->insertGetId($values);
            } else {
                if (! $this->db->table('employee_insurance_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('id', $profileId)->exists()) {
                    abort(404);
                }
                $this->db->table('employee_insurance_profiles')->where('id', $profileId)->update($values);
            }
            activity('payroll-insurance')->causedBy($actorId)->event('profile_saved')->withProperties(['company_id' => $companyId, 'employee_id' => $employeeId, 'profile_id' => $profileId, 'status' => $status->value])->log('Employee insurance profile saved');

            return $profileId;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $value = $this->text($data, $key);

        return $value === '' ? null : $value;
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key, int $default = 0): int
    {
        return $this->nullableInteger($data, $key) ?? $default;
    }

    /** @param array<string,mixed> $data */
    private function nullableInteger(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
