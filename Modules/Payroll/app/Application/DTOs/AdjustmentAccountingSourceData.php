<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollDownstreamSourceType;

final readonly class AdjustmentAccountingSourceData
{
    public function __construct(
        public PayrollDownstreamSourceType $sourceType,
        public ?int $payrollRunId,
        public ?int $payrollItemId,
        public ?int $adjustmentRequestId,
        public ?int $adjustmentRunId,
        public ?int $adjustmentComponentId,
        public ?int $sourcePayrollRunId,
        public ?int $sourcePayrollItemId,
        public ?int $historicalAmountMinor,
        public ?int $correctedAmountMinor,
        public ?int $deltaAmountMinor,
        public ?int $sourceCalculationVersion,
        public ?int $deltaCalculationVersion,
    ) {}
}
