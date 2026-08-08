<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class InsurancePayrollContext
{
    /**
     * @param  array{id:int,version:int,effective_from:string,currency_code:string,rounding_rule:string}|null  $policy
     * @param  list<array{insurance_type:string,employee_rate_basis_points:int,employer_rate_basis_points:int,minimum_base_minor:int|null,maximum_base_minor:int|null}>  $rates
     * @param  array<int,list<array{id:int,participation_status:string,insurance_policy_id:int|null,contribution_base_override_minor:int|null,currency_code:string|null}>>  $profiles
     */
    public function __construct(public ?array $policy, public array $rates, public array $profiles, public bool $policyOverlap) {}
}
