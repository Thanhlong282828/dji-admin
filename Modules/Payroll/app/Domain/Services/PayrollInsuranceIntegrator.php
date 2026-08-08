<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\InsuranceCalculationResult;
use Modules\Payroll\Application\DTOs\InsurancePayrollContext;
use Modules\Payroll\Domain\Calculation\PayrollCalculationResult;

final readonly class PayrollInsuranceIntegrator
{
    public function __construct(private ConnectionInterface $db, private InsuranceContributionBaseResolver $baseResolver, private StatutoryInsuranceCalculationService $calculator) {}

    /** @param list<int> $employeeIds */
    public function context(int $companyId, array $employeeIds, string $effectiveDate): InsurancePayrollContext
    {
        $policies = $this->db->table('insurance_policies')->where('company_id', $companyId)->where('status', 'active')->whereDate('effective_from', '<=', $effectiveDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))->orderByDesc('version')->get();
        $row = $policies->first();
        $policy = is_object($row) ? ['id' => $this->int($row, 'id'), 'version' => $this->int($row, 'version'), 'effective_from' => $this->string($row, 'effective_from'), 'currency_code' => $this->string($row, 'currency_code'), 'rounding_rule' => $this->string($row, 'rounding_rule')] : null;
        $rates = [];
        if ($policy !== null) {
            foreach ($this->db->table('insurance_policy_rates')->where('insurance_policy_id', $policy['id'])->orderBy('calculation_order')->get() as $rate) {
                $rates[] = ['insurance_type' => $this->string($rate, 'insurance_type'), 'employee_rate_basis_points' => $this->int($rate, 'employee_rate_basis_points'), 'employer_rate_basis_points' => $this->int($rate, 'employer_rate_basis_points'), 'minimum_base_minor' => $this->nullableInt($rate, 'minimum_base_minor'), 'maximum_base_minor' => $this->nullableInt($rate, 'maximum_base_minor')];
            }
        }
        $profiles = [];
        foreach ($this->db->table('employee_insurance_profiles')->where('company_id', $companyId)->whereIn('employee_id', $employeeIds)->whereDate('effective_from', '<=', $effectiveDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveDate))->orderByDesc('effective_from')->get() as $profile) {
            $employeeId = $this->int($profile, 'employee_id');
            $profiles[$employeeId][] = ['id' => $this->int($profile, 'id'), 'participation_status' => $this->string($profile, 'participation_status'), 'insurance_policy_id' => $this->nullableInt($profile, 'insurance_policy_id'), 'contribution_base_override_minor' => $this->nullableInt($profile, 'contribution_base_override_minor'), 'currency_code' => $this->nullableString($profile, 'currency_code')];
        }

        return new InsurancePayrollContext($policy, $rates, $profiles, $policies->count() > 1);
    }

    /** @param array<int,string> $statuses */
    public function calculate(InsurancePayrollContext $context, int $employeeId, PayrollCalculationResult $payroll, array $statuses, string $currency): InsuranceCalculationResult
    {
        if ($context->policy === null) {
            return new InsuranceCalculationResult(null, null, 0, [], [], 0, 0, []);
        }
        $issues = $context->policyOverlap ? ['INSURANCE_POLICY_OVERLAP'] : [];
        $profiles = $context->profiles[$employeeId] ?? [];
        if (count($profiles) !== 1) {
            $issues[] = $profiles === [] ? 'INSURANCE_PROFILE_MISSING' : 'INSURANCE_PROFILE_OVERLAP';
        } else {
            $profile = $profiles[0];
            if (in_array($profile['participation_status'], ['exempt', 'not_participating', 'suspended'], true)) {
                return new InsuranceCalculationResult($context->policy['id'], $context->policy['version'], 0, [], [], 0, 0, $issues);
            }
            if ($profile['insurance_policy_id'] !== null && $profile['insurance_policy_id'] !== $context->policy['id']) {
                $issues[] = 'INSURANCE_POLICY_MISSING';
            }
            if ($context->policy['currency_code'] !== $currency || ($profile['currency_code'] !== null && $profile['currency_code'] !== $currency)) {
                $issues[] = 'INSURANCE_CURRENCY_MISMATCH';
            }
            $components = [];
            foreach ($payroll->components as $line) {
                $status = $statuses[$line->input->componentId] ?? 'not_configured';
                if (! in_array($status, ['included', 'excluded'], true)) {
                    $issues[] = 'INSURANCE_COMPONENT_CLASSIFICATION_MISSING';
                }
                $components[] = ['id' => $line->input->componentId, 'amount_minor' => max(0, $line->amountMinor), 'insurance_status' => $status, 'currency' => $line->input->currency];
            }
            if ($context->rates === []) {
                $issues[] = 'INSURANCE_RATE_MISSING';
            }
            if ($issues === []) {
                $base = $this->baseResolver->resolve($components, $profile['contribution_base_override_minor'], $currency);
                if ($base->finalBaseMinor <= 0) {
                    $issues[] = 'INSURANCE_BASE_UNRESOLVED';
                } else {
                    return $this->calculator->calculate($base, $context->policy, $context->rates);
                }
            }
        }

        return new InsuranceCalculationResult($context->policy['id'], $context->policy['version'], 0, [], [], 0, 0, array_values(array_unique($issues)));
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
        return $this->nullableString($row, $key) ?? '';
    }

    private function nullableString(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }
}
