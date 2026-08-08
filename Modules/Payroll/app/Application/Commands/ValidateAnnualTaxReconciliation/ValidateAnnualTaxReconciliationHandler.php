<?php

namespace Modules\Payroll\Application\Commands\ValidateAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxValidationResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;

final readonly class ValidateAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxOperationsService $service) {}

    public function handle(ValidateAnnualTaxReconciliationCommand $command): AnnualTaxValidationResult
    {
        return $this->service->validate($command->companyId, $command->reconciliationId, $command->actorId, $command->validationStage);
    }
}
