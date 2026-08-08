<?php

namespace Modules\Payroll\Application\Commands\ApproveAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxLifecycleResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxAction;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;

final readonly class ApproveAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxLifecycleService $service) {}

    public function handle(ApproveAnnualTaxReconciliationCommand $command): AnnualTaxLifecycleResult
    {
        return $this->service->transition($command->companyId, $command->reconciliationId, PayrollAnnualTaxAction::Approve, $command->actorId);
    }
}
