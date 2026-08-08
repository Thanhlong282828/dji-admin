<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputDetail;

final readonly class GetPayrollInputDetailQuery
{
    public function __construct(public int $companyId, public int $snapshotId) {}
}
