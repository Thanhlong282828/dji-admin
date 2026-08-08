<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollRunListViewModel
{
    /** @param list<array{id:int,run_number:string,period:string,snapshot_version:int,status:string,employee_count:int,gross_minor:int,deduction_minor:int,net_minor:int,currency:string,calculation_version:int,created_at:string}> $runs */
    public function __construct(public array $runs, public int $page, public bool $hasMore) {}
}
