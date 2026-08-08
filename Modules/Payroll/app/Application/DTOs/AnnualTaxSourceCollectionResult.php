<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxSourceCollectionResult
{
    public function __construct(public int $reconciliationId, public int $sourceCount, public int $componentCount, public int $warningCount, public int $blockingIssueCount, public string $sourceChecksum, public bool $existingSnapshot, public bool $recollected) {}
}
