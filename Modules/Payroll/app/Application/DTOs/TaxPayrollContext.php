<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class TaxPayrollContext
{
    /**
     * @param  array<int,list<array{id:int,tax_status:string,residency_status:string,tax_policy_id:int|null,personal_deduction_eligible:bool,withholding_method:string}>>  $profiles
     * @param  array<int,int>  $dependentCounts
     * @param  array<int,string>  $componentStatuses
     * @param  array<int,array{id:int,version:int,effective_from:string,currency_code:string,tax_method:string,residency_group:string,rounding_rule:string,rounding_stage:string,floor_at_zero:bool,flat_rate_basis_points:int|null,brackets:list<array{sequence:int,lower:int,upper:int|null,rate:int}>,deductions:list<array{code:string,type:string,amount:int|null,rate:int|null,maximum:int|null}>}>  $policies
     * @param  list<string>  $issues
     */
    public function __construct(public array $profiles, public array $dependentCounts, public array $componentStatuses, public array $policies, public array $issues) {}
}
