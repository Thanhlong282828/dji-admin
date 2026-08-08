<?php

namespace Modules\Payroll\Application\Commands\CreateAdjustmentPaymentInstruction;

final readonly class CreateAdjustmentPaymentInstructionCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public int $actorId) {}
}
