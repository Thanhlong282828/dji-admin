<?php

namespace Modules\Payroll\Application\Commands\ProcessAnnualTaxReconciliation;

use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;

final readonly class ProcessAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxOperationsService $operations, private PayrollAnnualTaxLifecycleService $lifecycle, private PayrollAnnualTaxReconciliationService $sources) {}

    public function handle(ProcessAnnualTaxReconciliationCommand $command): object
    {
        return match ($command->action) {
            'collect' => $this->sources->collect($command->companyId, $command->reconciliationId, $command->actorId),
            'calculate' => $this->operations->calculate($command->companyId, $command->reconciliationId, $command->actorId),
            'validate' => $this->operations->validate($command->companyId, $command->reconciliationId, $command->actorId),
            'supersede' => $this->operations->supersede($command->companyId, $command->reconciliationId, $command->actorId, $command->reason ?? ''),
            default => $this->lifecycle->transition($command->companyId, $command->reconciliationId, $command->action, $command->actorId, $command->reason),
        };
    }
}
