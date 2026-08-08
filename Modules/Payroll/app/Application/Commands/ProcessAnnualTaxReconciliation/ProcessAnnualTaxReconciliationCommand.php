<?php

namespace Modules\Payroll\Application\Commands\ProcessAnnualTaxReconciliation;

final readonly class ProcessAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public string $action, public int $actorId, public ?string $reason = null) {}
}
