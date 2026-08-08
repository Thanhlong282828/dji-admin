<?php

namespace Modules\Payroll\Application\Commands\RejectAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxLifecycleResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxAction;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;

final readonly class RejectAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxLifecycleService $service) {}

    public function handle(RejectAnnualTaxReconciliationCommand $command): AnnualTaxLifecycleResult
    {
        return $this->service->transition($command->companyId, $command->reconciliationId, PayrollAnnualTaxAction::Reject, $command->actorId, $command->reason);
    }
}
