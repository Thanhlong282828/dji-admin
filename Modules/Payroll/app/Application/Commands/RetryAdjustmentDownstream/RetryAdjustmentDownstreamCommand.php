<?php

namespace Modules\Payroll\Application\Commands\RetryAdjustmentDownstream;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;

final readonly class RetryAdjustmentDownstreamCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public int $actorId, public PayrollAdjustmentDownstreamType $type) {}
}
