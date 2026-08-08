<?php

namespace Modules\Payroll\Application\Commands\SupersedeAnnualTaxReconciliation;

final readonly class SupersedeAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId, public string $reasonCode, public string $reason, public string $idempotencyKey) {}
}
