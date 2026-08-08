<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation;

final readonly class CreateAnnualTaxReconciliationCommand
{
    public function __construct(public int $companyId, public int $employeeId, public int $taxYear, public string $jurisdiction, public string $currency, public string $idempotencyKey, public int $actorId) {}
}
