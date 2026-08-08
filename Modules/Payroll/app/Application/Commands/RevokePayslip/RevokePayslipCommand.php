<?php

namespace Modules\Payroll\Application\Commands\RevokePayslip;

final readonly class RevokePayslipCommand
{
    public function __construct(public int $companyId, public int $payslipId, public int $actorId, public string $reason) {}
}
