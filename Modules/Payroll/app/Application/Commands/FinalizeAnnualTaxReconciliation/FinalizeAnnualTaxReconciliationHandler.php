<?php

namespace Modules\Payroll\Application\Commands\FinalizeAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxLifecycleResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxAction;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;

final readonly class FinalizeAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxLifecycleService $service) {}

    public function handle(FinalizeAnnualTaxReconciliationCommand $command): AnnualTaxLifecycleResult
    {
        return $this->service->transition($command->companyId, $command->reconciliationId, PayrollAnnualTaxAction::Finalize, $command->actorId);
    }
}
