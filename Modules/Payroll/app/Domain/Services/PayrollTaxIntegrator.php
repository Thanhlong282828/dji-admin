<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\TaxCalculationResult;
use Modules\Payroll\Application\DTOs\TaxPayrollContext;
use Modules\Payroll\Domain\Calculation\PayrollCalculationResult;
use Modules\Payroll\Domain\Enums\SalaryComponentCategory;

final readonly class PayrollTaxIntegrator
{
    public function __construct(private ConnectionInterface $db, private PersonalIncomeTaxCalculationService $calculator) {}

    /** @param list<int> $employeeIds */
    public function context(int $companyId, array $employeeIds, string $effectiveDate): TaxPayrollContext
    {
        $policies = [];
        $issues = [];
        $rows = $this->db->table('tax_policies')->where('company_id', $companyId)->where('status', 'active')->whereDate('effective_from', '<=', $effectiveDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))->orderByDesc('version')->get();
        foreach ($rows as $row) {
            $id = $this->int($row, 'id');
            $brackets = [];
            foreach ($this->db->table('tax_policy_brackets')->where('tax_policy_id', $id)->orderBy('sequence')->get() as $b) {
                $brackets[] = ['sequence' => $this->int($b, 'sequence'), 'lower' => $this->int($b, 'lower_bound_minor'), 'upper' => $this->nullableInt($b, 'upper_bound_minor'), 'rate' => $this->int($b, 'rate_basis_points')];
            }
            $deductions = [];
            foreach ($this->db->table('tax_policy_deductions')->where('tax_policy_id', $id)->where('status', 'active')->whereDate('effective_from', '<=', $effectiveDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))->orderBy('priority')->get() as $d) {
                $deductions[] = ['code' => $this->string($d, 'code'), 'type' => $this->string($d, 'deduction_type'), 'amount' => $this->nullableInt($d, 'amount_minor'), 'rate' => $this->nullableInt($d, 'rate_basis_points'), 'maximum' => $this->nullableInt($d, 'maximum_amount_minor')];
            }
            $policies[$id] = ['id' => $id, 'version' => $this->int($row, 'version'), 'effective_from' => $this->string($row, 'effective_from'), 'currency_code' => $this->string($row, 'currency_code'), 'tax_method' => $this->string($row, 'tax_method'), 'residency_group' => $this->string($row, 'residency_group'), 'rounding_rule' => $this->string($row, 'rounding_rule'), 'rounding_stage' => $this->string($row, 'rounding_stage'), 'floor_at_zero' => (bool) ($row->floor_taxable_income_at_zero ?? false), 'flat_rate_basis_points' => $this->nullableInt($row, 'flat_rate_basis_points'), 'brackets' => $brackets, 'deductions' => $deductions];
        }
        $profiles = [];
        foreach ($this->db->table('employee_tax_profiles')->where('company_id', $companyId)->whereIn('employee_id', $employeeIds)->whereDate('effective_from', '<=', $effectiveDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))->orderByDesc('effective_from')->get() as $p) {
            $profiles[$this->int($p, 'employee_id')][] = ['id' => $this->int($p, 'id'), 'tax_status' => $this->string($p, 'tax_status'), 'residency_status' => $this->string($p, 'residency_status'), 'tax_policy_id' => $this->nullableInt($p, 'tax_policy_id'), 'personal_deduction_eligible' => (bool) ($p->personal_deduction_eligible ?? false), 'withholding_method' => $this->string($p, 'withholding_method')];
        }
        $dependents = [];
        foreach ($this->db->table('employee_tax_dependents')->where('company_id', $companyId)->whereIn('employee_id', $employeeIds)->where('deduction_eligible', true)->where('approval_status', 'approved')->whereDate('effective_from', '<=', $effectiveDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))->selectRaw('employee_id, COUNT(*) dependent_count')->groupBy('employee_id')->get() as $d) {
            $dependents[$this->int($d, 'employee_id')] = $this->int($d, 'dependent_count');
        }
        $statuses = [];
        foreach ($this->db->table('salary_components')->where('company_id', $companyId)->pluck('taxable_status', 'id') as $id => $status) {
            if (is_numeric($id) && is_string($status)) {
                $statuses[(int) $id] = $status;
            }
        }

        return new TaxPayrollContext($profiles, $dependents, $statuses, $policies, $issues);
    }

    public function calculate(TaxPayrollContext $context, int $employeeId, PayrollCalculationResult $payroll, int $employeeInsuranceMinor, string $currency): TaxCalculationResult
    {
        if ($context->policies === []) {
            return new TaxCalculationResult(null, null, 0, 0, 0, [], []);
        }
        $profiles = $context->profiles[$employeeId] ?? [];
        if (count($profiles) !== 1) {
            return new TaxCalculationResult(null, null, 0, 0, 0, [], [$profiles === [] ? 'TAX_PROFILE_MISSING' : 'TAX_PROFILE_OVERLAP']);
        }
        $profile = $profiles[0];
        if (in_array($profile['tax_status'], ['exempt', 'not_subject', 'suspended'], true)) {
            return new TaxCalculationResult(null, null, 0, 0, 0, [], []);
        }
        $policyId = $profile['tax_policy_id'];
        $policy = $policyId === null ? null : ($context->policies[$policyId] ?? null);
        if ($policy === null || $policy['residency_group'] !== $profile['residency_status']) {
            return new TaxCalculationResult($policyId, null, 0, 0, 0, [], ['TAX_POLICY_MISSING']);
        }
        if ($policy['currency_code'] !== $currency) {
            return new TaxCalculationResult($policy['id'], $policy['version'], 0, 0, 0, [], ['TAX_CURRENCY_MISMATCH']);
        }
        $taxable = 0;
        $issues = [];
        foreach ($payroll->components as $line) {
            if ($line->input->category !== SalaryComponentCategory::Earning) {
                continue;
            }
            $status = $context->componentStatuses[$line->input->componentId] ?? 'not_configured';
            if ($status === 'taxable') {
                $taxable += max(0, $line->amountMinor);
            } elseif (! in_array($status, ['non_taxable', 'excluded'], true)) {
                $issues[] = 'TAX_COMPONENT_CLASSIFICATION_MISSING';
            }
        }
        $personal = 0;
        $dependent = 0;
        $other = 0;
        foreach ($policy['deductions'] as $deduction) {
            $amount = $deduction['amount'] ?? 0;
            match ($deduction['type']) {
                'personal' => $personal += $profile['personal_deduction_eligible'] ? $amount : 0,
                'dependent' => $dependent += $amount * ($context->dependentCounts[$employeeId] ?? 0),
                'employee_insurance' => null,
                default => $other += $amount,
            };
        }
        $beforeFloor = $taxable - $employeeInsuranceMinor - $personal - $dependent - $other;
        $resolved = $policy['floor_at_zero'] ? max(0, $beforeFloor) : $beforeFloor;
        if ($resolved < 0) {
            $issues[] = 'TAXABLE_INCOME_UNRESOLVED';
        }
        if ($issues !== []) {
            return new TaxCalculationResult($policy['id'], $policy['version'], $beforeFloor, max(0, $resolved), 0, [], array_values(array_unique($issues)), $employeeInsuranceMinor, $personal, $dependent, $other);
        }
        $result = $this->calculator->calculate($resolved, $policy);

        return new TaxCalculationResult($result->policyId, $result->policyVersion, $beforeFloor, $resolved, $result->withholdingTaxMinor, $result->bracketLines, $result->issues, $employeeInsuranceMinor, $personal, $dependent, $other);
    }

    private function int(object $row, string $key): int
    {
        return $this->nullableInt($row, $key) ?? 0;
    }

    private function nullableInt(object $row, string $key): ?int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
