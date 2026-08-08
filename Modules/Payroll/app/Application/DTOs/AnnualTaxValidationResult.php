<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxValidationResult
{
    public function __construct(public int $reconciliationId, public int $blockingIssueCount, public int $warningCount, public bool $valid) {}
}
