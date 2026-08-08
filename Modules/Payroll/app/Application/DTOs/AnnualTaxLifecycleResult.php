<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAnnualTaxReconciliationStatus;

final readonly class AnnualTaxLifecycleResult
{
    public function __construct(public int $reconciliationId, public PayrollAnnualTaxReconciliationStatus $fromStatus, public PayrollAnnualTaxReconciliationStatus $toStatus, public bool $existingTransition = false) {}
}
