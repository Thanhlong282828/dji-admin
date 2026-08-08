<?php

namespace Modules\Payroll\Application\Commands\ReviewAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxLifecycleResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxAction;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;

final readonly class ReviewAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxLifecycleService $service) {}

    public function handle(ReviewAnnualTaxReconciliationCommand $command): AnnualTaxLifecycleResult
    {
        return $this->service->transition($command->companyId, $command->reconciliationId, PayrollAnnualTaxAction::Review, $command->actorId);
    }
}
