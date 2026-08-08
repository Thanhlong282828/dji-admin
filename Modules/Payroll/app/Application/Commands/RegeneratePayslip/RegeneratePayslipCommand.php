<?php

namespace Modules\Payroll\Application\Commands\RegeneratePayslip;

final readonly class RegeneratePayslipCommand
{
    public function __construct(public int $companyId, public int $payslipId, public int $actorId) {}
}
