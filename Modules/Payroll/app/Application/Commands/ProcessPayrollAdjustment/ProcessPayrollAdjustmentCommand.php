<?php

namespace Modules\Payroll\Application\Commands\ProcessPayrollAdjustment;

final readonly class ProcessPayrollAdjustmentCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public string $action, public int $actorId, public ?string $reason = null, public ?int $correctedGross = null, public ?int $correctedEmployeeInsurance = null, public ?int $correctedEmployerInsurance = null, public ?int $correctedTax = null, public ?int $correctedNet = null) {}
}
