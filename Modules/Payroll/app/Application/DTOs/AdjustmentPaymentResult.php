<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;

final readonly class AdjustmentPaymentResult
{
    public function __construct(
        public int $intentId,
        public PayrollAdjustmentDownstreamStatus $status,
        public ?int $paymentBatchId,
        public ?int $paymentBatchItemId,
        public ?int $amountMinor,
        public ?string $currency,
        public bool $existingArtifact,
        public ?string $failureCode = null,
    ) {}
}
