<?php

namespace Modules\Payroll\Application\Commands\ValidateAnnualTaxReconciliation;

use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationStage;

final readonly class ValidateAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId, public PayrollAnnualTaxValidationStage $validationStage = PayrollAnnualTaxValidationStage::PreSubmit) {}
}
