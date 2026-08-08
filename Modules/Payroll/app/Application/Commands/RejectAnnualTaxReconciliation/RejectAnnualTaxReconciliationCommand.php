<?php

namespace Modules\Payroll\Application\Commands\RejectAnnualTaxReconciliation;

final readonly class RejectAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId, public string $reason) {}
}
