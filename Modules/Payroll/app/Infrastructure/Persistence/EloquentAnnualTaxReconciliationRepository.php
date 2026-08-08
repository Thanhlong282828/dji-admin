<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Modules\Payroll\Domain\Models\PayrollAnnualTaxReconciliation;
use Modules\Payroll\Domain\Repositories\AnnualTaxReconciliationRepositoryInterface;

final readonly class EloquentAnnualTaxReconciliationRepository implements AnnualTaxReconciliationRepositoryInterface
{
    public function findByIdempotency(int $companyId, string $idempotencyKey): ?PayrollAnnualTaxReconciliation
    {
        return PayrollAnnualTaxReconciliation::query()->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
    }

    public function findActive(int $companyId, int $employeeId, int $taxYear): ?PayrollAnnualTaxReconciliation
    {
        return PayrollAnnualTaxReconciliation::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->where('tax_year', $taxYear)->whereNotNull('active_key')->first();
    }

    public function findScoped(int $companyId, int $reconciliationId, bool $lock = false): PayrollAnnualTaxReconciliation
    {
        $query = PayrollAnnualTaxReconciliation::query()->where('company_id', $companyId)->whereKey($reconciliationId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
