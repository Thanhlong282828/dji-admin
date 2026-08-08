<?php

namespace Modules\Payroll\Application\Queries\GetPayrollRunDetail;

final readonly class GetPayrollRunDetailQuery
{
    public function __construct(public int $companyId, public int $runId) {}
}
