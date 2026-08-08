<?php

namespace Modules\Payroll\Application\Queries\GetPayslipDetail;

final readonly class GetPayslipDetailQuery
{
    public function __construct(public int $companyId, public int $payslipId) {}
}
