<?php

namespace Modules\Payroll\Application\Queries\GetPayrollItemDetail;

final readonly class GetPayrollItemDetailQuery
{
    public function __construct(public int $companyId, public int $runId, public int $itemId) {}
}
