<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollInputOperationsViewModel
{
    /**
     * @param  list<array{id:int,label:string,period:string,version:int,status:string,employee_count:int,attendance_count:int,leave_count:int,overtime_count:int,blocking_issues:int,captured_at:string,captured_by:string}>  $snapshots
     * @param  list<array{id:int,code:string,name:string,start:string,end:string,status:string,version:int,latest_snapshot_version:int}>  $periods
     * @param  array{id:int,label:string,period:string,period_id:int,version:int,status:string,employee_count:int,attendance_count:int,leave_count:int,overtime_count:int,blocking_issues:int,warning_issues:int,scheduled_minutes:int,worked_minutes:int,paid_leave_minutes:int,unpaid_leave_minutes:int,overtime_minutes:int,captured_at:string,captured_by:string,note:string|null,run_id:int|null}|null  $summary
     * @param  list<array{id:int,employee_id:int,number:string,name:string,organization:string|null,position:string|null,scheduled:int,worked:int,paid_leave:int,unpaid_leave:int,overtime:int,attendance_count:int,leave_count:int,overtime_count:int,status:string}>  $employees
     * @param  list<array{code:string,severity:string,scope:string,blocking:bool,message:string,employee_id:int|null}>  $issues
     * @param  list<array{action:string,from_status:string|null,to_status:string,actor:string,created_at:string}>  $history
     */
    public function __construct(public array $snapshots = [], public array $periods = [], public ?array $summary = null, public array $employees = [], public array $issues = [], public array $history = []) {}
}
