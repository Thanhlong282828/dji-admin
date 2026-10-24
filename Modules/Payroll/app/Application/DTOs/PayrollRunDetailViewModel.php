<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollRunDetailViewModel
{
    /**
     * @param  array{id:int,run_number:string,status:string,currency_code:string,employee_count:int,gross_total_minor:int,deduction_total_minor:int,employer_contribution_total_minor:int,net_total_minor:int,calculation_version:int,version:int,period_id:int,period:string,snapshot_id:int,snapshot_version:int,snapshot_captured_at:string|null,created_by:string,created_at:string|null,calculated_at:string|null,submitted_at:string|null,approved_at:string|null,finalized_at:string|null}  $summary
     * @param  list<array{id:int,employee_number:string,employee_name:string,branch:string|null,gross_minor:int,deduction_minor:int,employer_minor:int,net_minor:int,status:string}>  $items
     * @param  list<array{severity:string,code:string,message:string}>  $issues
     * @param  list<array{action:string,from_status:string|null,to_status:string,actor:string,reason:string|null,calculation_version:int,version_before:int,version_after:int,created_at:string}>  $history
     * @param  list<array{action:string,label:string,confirmation:string|null,requires_reason:bool}>  $actions
     */
    public function __construct(public array $summary, public array $items, public array $issues, public array $history, public array $actions = []) {}
}
