<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollPeriod;

final readonly class CreatePayrollPeriodCommand
{
    /** @param array{code:string,name:string,start:string,end:string,pay_date:string,status:string,note:string|null} $data */
    public function __construct(public int $companyId, public array $data, public int $actorId, public string $idempotencyKey) {}
}
