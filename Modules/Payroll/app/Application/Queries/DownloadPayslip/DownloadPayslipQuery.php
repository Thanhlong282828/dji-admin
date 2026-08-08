<?php

namespace Modules\Payroll\Application\Queries\DownloadPayslip;

final readonly class DownloadPayslipQuery
{
    public function __construct(public int $companyId, public int $payslipId, public int $actorId, public bool $employeeOwner = false) {}
}
