<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollItemDetailViewModel
{
    /**
     * @param  array{id:int,run_id:int,run_number:string,run_status:string,employee_id:int,employee_number:string,employee_name:string,organization:string|null,position:string|null,currency:string,scheduled:int,worked:int,paid_leave:int,unpaid_leave:int,overtime:int,gross:int,deduction:int,employer:int,net:int,insurance_base:int,insurance_employee:int,insurance_employer:int,insurance_policy_id:int|null,insurance_policy_version:int|null,tax_policy_id:int|null,tax_policy_version:int|null,taxable_income_before_floor:int,taxable_income:int,personal_income_tax:int,net_before_tax:int,net_after_tax:int,status:string,calculation_version:int,calculated_at:string|null}  $summary
     * @param  list<array{code:string,name:string,category:string,type:string,base:int|null,rate:int|null,amount:int,currency:string,order:int}>  $components
     * @param  list<array{severity:string,code:string,message:string}>  $issues
     */
    public function __construct(public array $summary, public array $components, public array $issues) {}
}
