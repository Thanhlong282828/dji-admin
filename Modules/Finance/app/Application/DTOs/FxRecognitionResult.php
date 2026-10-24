<?php

namespace Modules\Finance\Application\DTOs;

final readonly class FxRecognitionResult
{
    public function __construct(
        public int $recognitionId,
        public string $transactionCurrency,
        public int $transactionAmountMinor,
        public string $functionalCurrency,
        public int $functionalAmountMinor,
        public ?int $snapshotId,
        public string $rateDecimal,
        public string $rateDate,
        public string $rateType,
        public string $sourceReference,
        public bool $identity,
    ) {}
}
