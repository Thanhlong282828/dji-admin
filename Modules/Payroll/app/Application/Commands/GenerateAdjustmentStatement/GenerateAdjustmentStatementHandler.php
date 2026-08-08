<?php

namespace Modules\Payroll\Application\Commands\GenerateAdjustmentStatement;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;
use Modules\Payroll\Domain\Services\PayrollAdjustmentDownstreamService;

final readonly class GenerateAdjustmentStatementHandler
{
    public function __construct(private PayrollAdjustmentDownstreamService $service) {}

    public function handle(GenerateAdjustmentStatementCommand $command): void
    {
        $this->service->execute($command->companyId, $command->adjustmentId, $command->actorId, PayrollAdjustmentDownstreamType::Statement);
    }
}
