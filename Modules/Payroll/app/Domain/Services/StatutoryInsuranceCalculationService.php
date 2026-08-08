<?php

namespace Modules\Payroll\Domain\Services;

use Modules\Payroll\Application\DTOs\InsuranceCalculationResult;
use Modules\Payroll\Application\DTOs\InsuranceContributionBase;
use Modules\Payroll\Application\DTOs\InsuranceContributionLine;
use Modules\Payroll\Domain\Enums\InsuranceType;

final readonly class StatutoryInsuranceCalculationService
{
    public function __construct(private InsuranceRoundingService $rounding) {}

    /**
     * @param  array{id:int,version:int,effective_from:string,currency_code:string,rounding_rule:string}  $policy
     * @param  list<array{insurance_type:string,employee_rate_basis_points:int,employer_rate_basis_points:int,minimum_base_minor:int|null,maximum_base_minor:int|null}>  $rates
     */
    public function calculate(InsuranceContributionBase $base, array $policy, array $rates): InsuranceCalculationResult
    {
        $employee = [];
        $employer = [];
        $employeeTotal = 0;
        $employerTotal = 0;
        $issues = [];
        foreach ($rates as $rate) {
            $type = InsuranceType::tryFrom($rate['insurance_type']);
            if ($type === null) {
                $issues[] = 'INSURANCE_RATE_MISSING';

                continue;
            }
            $resolvedBase = $base->finalBaseMinor;
            if ($rate['minimum_base_minor'] !== null) {
                $resolvedBase = max($resolvedBase, $rate['minimum_base_minor']);
            }
            if ($rate['maximum_base_minor'] !== null) {
                $resolvedBase = min($resolvedBase, $rate['maximum_base_minor']);
            }
            $employeeAmount = $this->rounding->percentage($resolvedBase, $rate['employee_rate_basis_points'], $policy['rounding_rule']);
            $employerAmount = $this->rounding->percentage($resolvedBase, $rate['employer_rate_basis_points'], $policy['rounding_rule']);
            $employee[] = new InsuranceContributionLine($type, 'employee', $resolvedBase, $rate['employee_rate_basis_points'], $employeeAmount, $policy['id'], $policy['version'], $policy['effective_from']);
            $employer[] = new InsuranceContributionLine($type, 'employer', $resolvedBase, $rate['employer_rate_basis_points'], $employerAmount, $policy['id'], $policy['version'], $policy['effective_from']);
            $employeeTotal += $employeeAmount;
            $employerTotal += $employerAmount;
        }

        return new InsuranceCalculationResult($policy['id'], $policy['version'], $base->finalBaseMinor, $employee, $employer, $employeeTotal, $employerTotal, $issues);
    }
}
