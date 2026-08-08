<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollDownstreamSourceType;

final readonly class AdjustmentPaymentSourceData
{
    public function __construct(
        public PayrollDownstreamSourceType $sourceType,
        public ?int $payrollItemId,
        public ?int $adjustmentRequestId,
        public ?int $adjustmentRunId,
        public ?int $sourcePayrollItemId,
        public ?int $sourceCalculationVersion,
        public ?int $deltaCalculationVersion,
        public ?string $activeAdjustmentPaymentKey = null,
    ) {}
}
