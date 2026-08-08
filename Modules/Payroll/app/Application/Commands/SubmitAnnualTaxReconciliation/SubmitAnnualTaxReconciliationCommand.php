<?php

namespace Modules\Payroll\Application\Commands\SubmitAnnualTaxReconciliation;

final readonly class SubmitAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
