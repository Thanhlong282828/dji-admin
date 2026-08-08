<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollDeliveryViewModel
{
    /**
     * @param  list<array{id:int,code:string,name:string,category:string,calculation_type:string,currency_code:string|null,status:string,version:int}>  $components
     * @param  list<array{id:int,employee_id:int,employee_name:string,component_name:string,effective_from:string,effective_to:string|null,status:string,version:int}>  $assignments
     * @param  list<array{id:int,run_number:string,status:string,currency_code:string,employee_count:int,calculation_version:int,version:int}>  $runs
     */
    public function __construct(public array $components, public array $assignments, public array $runs) {}
}
