<?php

namespace Modules\Payroll\Application\Commands\PublishPayslip;

final readonly class PublishPayslipCommand
{
    public function __construct(public int $companyId, public int $payslipId, public int $actorId) {}
}
