<?php

namespace Modules\Payroll\Application\Commands\RetryAdjustmentDownstream;

use Modules\Payroll\Domain\Services\PayrollAdjustmentDownstreamService;

final readonly class RetryAdjustmentDownstreamHandler
{
    public function __construct(private PayrollAdjustmentDownstreamService $service) {}

    public function handle(RetryAdjustmentDownstreamCommand $command): void
    {
        $this->service->execute($command->companyId, $command->adjustmentId, $command->actorId, $command->type);
    }
}
