<?php

namespace Modules\Payroll\Application\Commands\SubmitAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxLifecycleResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxAction;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;

final readonly class SubmitAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxLifecycleService $service) {}

    public function handle(SubmitAnnualTaxReconciliationCommand $command): AnnualTaxLifecycleResult
    {
        return $this->service->transition($command->companyId, $command->reconciliationId, PayrollAnnualTaxAction::Submit, $command->actorId);
    }
}
