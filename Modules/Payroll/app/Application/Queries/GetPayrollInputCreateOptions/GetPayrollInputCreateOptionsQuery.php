<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputCreateOptions;

final readonly class GetPayrollInputCreateOptionsQuery
{
    public function __construct(public int $companyId) {}
}
