<?php

namespace Modules\Payroll\Application\Commands\SupersedeAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxSupersedeResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;

final readonly class SupersedeAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxOperationsService $service) {}

    public function handle(SupersedeAnnualTaxReconciliationCommand $command): AnnualTaxSupersedeResult
    {
        return $this->service->supersede($command->companyId, $command->reconciliationId, $command->actorId, $command->reasonCode.': '.$command->reason, $command->idempotencyKey);
    }
}
