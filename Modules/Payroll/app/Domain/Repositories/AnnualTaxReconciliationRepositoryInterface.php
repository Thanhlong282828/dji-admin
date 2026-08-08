<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Domain\Models\PayrollAnnualTaxReconciliation;

interface AnnualTaxReconciliationRepositoryInterface
{
    public function findByIdempotency(int $companyId, string $idempotencyKey): ?PayrollAnnualTaxReconciliation;

    public function findActive(int $companyId, int $employeeId, int $taxYear): ?PayrollAnnualTaxReconciliation;

    public function findScoped(int $companyId, int $reconciliationId, bool $lock = false): PayrollAnnualTaxReconciliation;
}
