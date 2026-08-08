<?php

namespace Modules\Payroll\Application\Commands\FinalizeAnnualTaxReconciliation;

final readonly class FinalizeAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
