<?php

namespace Modules\Payroll\Application\Commands\CreateAdjustmentAccountingJournal;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;
use Modules\Payroll\Domain\Services\PayrollAdjustmentDownstreamService;

final readonly class CreateAdjustmentAccountingJournalHandler
{
    public function __construct(private PayrollAdjustmentDownstreamService $service) {}

    public function handle(CreateAdjustmentAccountingJournalCommand $command): void
    {
        $this->service->execute($command->companyId, $command->adjustmentId, $command->actorId, PayrollAdjustmentDownstreamType::Accounting);
    }
}
