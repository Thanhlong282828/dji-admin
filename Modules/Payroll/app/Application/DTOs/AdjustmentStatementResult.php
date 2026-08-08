<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;

final readonly class AdjustmentStatementResult
{
    public function __construct(
        public int $intentId,
        public PayrollAdjustmentDownstreamStatus $status,
        public ?int $statementId,
        public ?string $fileName,
        public ?int $fileSize,
        public bool $existingArtifact,
        public ?string $failureCode = null,
    ) {}
}
