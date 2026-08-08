<?php

namespace Modules\Payroll\Application\Commands\CreateAdjustmentPaymentInstruction;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;
use Modules\Payroll\Domain\Services\PayrollAdjustmentDownstreamService;

final readonly class CreateAdjustmentPaymentInstructionHandler
{
    public function __construct(private PayrollAdjustmentDownstreamService $service) {}

    public function handle(CreateAdjustmentPaymentInstructionCommand $command): void
    {
        $this->service->execute($command->companyId, $command->adjustmentId, $command->actorId, PayrollAdjustmentDownstreamType::Payment);
    }
}
