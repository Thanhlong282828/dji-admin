<?php

namespace Modules\Finance\Application\Services;

final readonly class FxRevaluableSource
{
    public function __construct(
        public string $sourceType,
        public int $sourceId,
        public int $companyId,
        public ?int $branchId,
        public string $transactionCurrency,
        public int $foreignOutstandingMinor,
        public string $functionalCurrency,
        public int $carryingFunctionalMinor,
        public string $balanceNature,
        public int $recognitionSnapshotId,
        public string $recognitionChecksum,
        /** @var array<string,mixed> */
        public array $evidence = [],
    ) {}
}
