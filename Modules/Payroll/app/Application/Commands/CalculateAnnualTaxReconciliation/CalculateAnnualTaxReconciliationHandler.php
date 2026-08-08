<?php

namespace Modules\Payroll\Application\Commands\CalculateAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxCalculationResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;

final readonly class CalculateAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxOperationsService $service) {}

    public function handle(CalculateAnnualTaxReconciliationCommand $command): AnnualTaxCalculationResult
    {
        return $this->service->calculate($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
