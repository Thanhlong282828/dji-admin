<?php

namespace Modules\Payroll\Application\DTOs;

use DateTimeImmutable;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;

final readonly class AdjustmentDownstreamIntentData
{
    public function __construct(
        public PayrollAdjustmentDownstreamType $type,
        public PayrollAdjustmentDownstreamStatus $status,
        public ?string $idempotencyKey,
        public int $attemptCount,
        public ?DateTimeImmutable $lastAttemptAt,
        public ?DateTimeImmutable $processingStartedAt,
        public ?DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $failedAt,
        public ?string $failureCode,
        public ?string $failureMessage,
        public ?string $artifactType,
        public ?int $paymentBatchItemId,
        public ?int $accountingJournalId,
        public ?int $adjustmentStatementId,
        public ?int $recoveryId,
    ) {}
}
