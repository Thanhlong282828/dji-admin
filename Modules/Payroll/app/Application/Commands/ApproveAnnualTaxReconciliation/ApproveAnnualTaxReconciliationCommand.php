<?php

namespace Modules\Payroll\Application\Commands\ApproveAnnualTaxReconciliation;

final readonly class ApproveAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
