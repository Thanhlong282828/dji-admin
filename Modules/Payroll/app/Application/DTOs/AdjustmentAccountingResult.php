<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;

final readonly class AdjustmentAccountingResult
{
    public function __construct(
        public int $intentId,
        public PayrollAdjustmentDownstreamStatus $status,
        public ?int $journalId,
        public ?string $journalNumber,
        public ?int $totalDebitMinor,
        public ?int $totalCreditMinor,
        public bool $balanced,
        public bool $existingArtifact,
        public ?string $failureCode = null,
    ) {}
}
