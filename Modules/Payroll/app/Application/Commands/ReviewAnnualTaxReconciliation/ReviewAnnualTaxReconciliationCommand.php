<?php

namespace Modules\Payroll\Application\Commands\ReviewAnnualTaxReconciliation;

final readonly class ReviewAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
