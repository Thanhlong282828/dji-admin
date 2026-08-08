<?php

namespace Modules\Payroll\Application\Commands\UpdatePayrollPeriod;

final readonly class UpdatePayrollPeriodCommand
{
    /** @param array{code:string,name:string,start:string,end:string,pay_date:string,note:string|null} $data */
    public function __construct(public int $companyId, public int $periodId, public array $data, public int $actorId) {}
}
