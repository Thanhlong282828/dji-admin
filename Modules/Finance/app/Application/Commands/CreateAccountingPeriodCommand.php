<?php

namespace Modules\Finance\Application\Commands;

final readonly class CreateAccountingPeriodCommand
{
    /** @param array{year:int,month:int,start_date:string,end_date:string} $data */
    public function __construct(public int $companyId, public int $fiscalYearId, public array $data) {}
}
