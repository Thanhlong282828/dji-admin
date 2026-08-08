<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

final readonly class TaxManagementService
{
    public function __construct(private ConnectionInterface $db, private TaxBracketValidator $brackets) {}

    /** @param array<string,mixed> $data */
    public function savePolicy(int $company, ?int $id, array $data, int $actor): int
    {
        return $this->db->transaction(function () use ($company, $id, $data, $actor): int {
            $from = $this->text($data, 'effective_from');
            $to = $this->nullableText($data, 'effective_to');
            if ($to !== null && $to < $from) {
                throw ValidationException::withMessages(['effective_to' => 'Effective To phải sau Effective From.']);
            }
            $method = $this->text($data, 'tax_method');
            $residency = $this->text($data, 'residency_group');
            $overlap = $this->db->table('tax_policies')->where('company_id', $company)->where('residency_group', $residency)->where('status', 'active')->when($id !== null, fn (Builder $q): Builder => $q->where('id', '<>', $id))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->when($to !== null, fn (Builder $q): Builder => $q->whereDate('effective_from', '<=', $to))->exists();
            if ($this->text($data, 'status') === 'active' && $overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Active tax policy cùng residency bị overlap.']);
            }
            $rawBrackets = $data['brackets'] ?? [];
            $brackets = [];
            if (is_array($rawBrackets)) {
                foreach ($rawBrackets as $b) {
                    if (is_array($b)) {
                        $brackets[] = $this->normalize($b);
                    }
                }
            }
            if ($method === 'progressive') {
                $this->brackets->validate($brackets);
            }
            if ($method === 'flat' && $this->nullableInt($data, 'flat_rate_basis_points') === null) {
                throw ValidationException::withMessages(['flat_rate_basis_points' => 'Flat policy cần rate.']);
            }
            $values = ['company_id' => $company, 'code' => strtoupper($this->text($data, 'code')), 'name' => $this->text($data, 'name'), 'version' => $this->int($data, 'version', 1), 'effective_from' => $from, 'effective_to' => $to, 'currency_code' => strtoupper($this->text($data, 'currency_code')), 'tax_method' => $method, 'residency_group' => $residency, 'rounding_rule' => $this->text($data, 'rounding_rule'), 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => (bool) ($data['floor_taxable_income_at_zero'] ?? false), 'flat_rate_basis_points' => $this->nullableInt($data, 'flat_rate_basis_points'), 'status' => $this->text($data, 'status'), 'note' => $this->nullableText($data, 'note'), 'updated_by' => $actor, 'updated_at' => now()];
            $creating = $id === null;
            if ($creating) {
                $values['created_by'] = $actor;
                $values['created_at'] = now();
                $id = (int) $this->db->table('tax_policies')->insertGetId($values);
            } else {
                $policy = $this->db->table('tax_policies')->where('company_id', $company)->lockForUpdate()->find($id);
                if (! is_object($policy)) {
                    abort(404);
                } $used = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.tax_policy_id', $id)->whereIn('r.status', ['submitted', 'approved', 'finalized'])->exists();
                if ($used) {
                    throw ValidationException::withMessages(['policy' => 'Policy đã dùng; hãy tạo version mới.']);
                } $this->db->table('tax_policies')->where('id', $id)->update($values);
                $this->db->table('tax_policy_brackets')->where('tax_policy_id', $id)->delete();
                $this->db->table('tax_policy_deductions')->where('tax_policy_id', $id)->delete();
            }
            foreach ($brackets as $order => $b) {
                $this->db->table('tax_policy_brackets')->insert(['tax_policy_id' => $id, 'sequence' => $order + 1, 'lower_bound_minor' => $this->int($b, 'lower_bound_minor'), 'upper_bound_minor' => $this->nullableInt($b, 'upper_bound_minor'), 'rate_basis_points' => $this->int($b, 'rate_basis_points'), 'created_at' => now(), 'updated_at' => now()]);
            }
            $rawDeductions = $data['deductions'] ?? [];
            if (is_array($rawDeductions)) {
                foreach ($rawDeductions as $order => $rawDeduction) {
                    if (! is_array($rawDeduction)) {
                        continue;
                    }$d = $this->normalize($rawDeduction);
                    $this->db->table('tax_policy_deductions')->insert(['tax_policy_id' => $id, 'code' => strtoupper($this->text($d, 'code')), 'name' => $this->text($d, 'name'), 'deduction_type' => $this->text($d, 'deduction_type'), 'amount_minor' => $this->nullableInt($d, 'amount_minor'), 'rate_basis_points' => $this->nullableInt($d, 'rate_basis_points'), 'maximum_amount_minor' => $this->nullableInt($d, 'maximum_amount_minor'), 'effective_from' => $from, 'effective_to' => $to, 'priority' => $order, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            activity('payroll-tax')->causedBy($actor)->event($creating ? 'created' : 'updated')->withProperties(['company_id' => $company, 'policy_id' => $id, 'version' => $values['version']])->log('Tax policy saved');

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function saveProfile(int $company, int $employee, ?int $id, array $data, int $actor): int
    {
        return $this->db->transaction(function () use ($company, $employee, $id, $data, $actor): int {
            if (! $this->db->table('employees')->where('company_id', $company)->where('id', $employee)->exists()) {
                abort(404);
            } $policy = $this->nullableInt($data, 'tax_policy_id');
            if ($policy !== null && ! $this->db->table('tax_policies')->where('company_id', $company)->where('id', $policy)->exists()) {
                abort(404);
            } $from = $this->text($data, 'effective_from');
            $to = $this->nullableText($data, 'effective_to');
            $overlap = $this->db->table('employee_tax_profiles')->where('company_id', $company)->where('employee_id', $employee)->when($id !== null, fn (Builder $q): Builder => $q->where('id', '<>', $id))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->when($to !== null, fn (Builder $q): Builder => $q->whereDate('effective_from', '<=', $to))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Employee tax profile effective range bị overlap.']);
            }
            $values = ['company_id' => $company, 'employee_id' => $employee, 'tax_policy_id' => $policy, 'tax_status' => $this->text($data, 'tax_status'), 'residency_status' => $this->text($data, 'residency_status'), 'effective_from' => $from, 'effective_to' => $to, 'tax_identification_number' => $this->nullableText($data, 'tax_identification_number'), 'personal_deduction_eligible' => (bool) ($data['personal_deduction_eligible'] ?? false), 'withholding_method' => $this->text($data, 'withholding_method'), 'base_override_minor' => $this->nullableInt($data, 'base_override_minor'), 'exemption_reason' => $this->nullableText($data, 'exemption_reason'), 'updated_by' => $actor, 'updated_at' => now()];
            if ($id === null) {
                $values['created_by'] = $actor;
                $values['created_at'] = now();
                $id = (int) $this->db->table('employee_tax_profiles')->insertGetId($values);
            } else {
                if (! $this->db->table('employee_tax_profiles')->where('company_id', $company)->where('employee_id', $employee)->where('id', $id)->exists()) {
                    abort(404);
                }$this->db->table('employee_tax_profiles')->where('id', $id)->update($values);
            } activity('payroll-tax')->causedBy($actor)->event('profile_saved')->withProperties(['company_id' => $company, 'employee_id' => $employee, 'profile_id' => $id])->log('Employee tax profile saved');

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    private function text(array $d, string $k): string
    {
        $v = $d[$k] ?? null;

        return is_string($v) ? trim($v) : '';
    }

    /** @param array<string,mixed> $d */
    private function nullableText(array $d, string $k): ?string
    {
        $v = $this->text($d, $k);

        return $v === '' ? null : $v;
    }

    /** @param array<string,mixed> $d */
    private function int(array $d, string $k, int $default = 0): int
    {
        return $this->nullableInt($d, $k) ?? $default;
    }

    /** @param array<string,mixed> $d */
    private function nullableInt(array $d, string $k): ?int
    {
        $v = $d[$k] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
