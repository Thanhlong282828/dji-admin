<?php

namespace Modules\Payroll\Application\Commands\CalculateAnnualTaxReconciliation;

final readonly class CalculateAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
