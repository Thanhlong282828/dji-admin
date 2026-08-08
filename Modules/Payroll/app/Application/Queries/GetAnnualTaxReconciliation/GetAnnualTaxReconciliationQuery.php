<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxReconciliation;

final readonly class GetAnnualTaxReconciliationQuery
{
    /** @param array<string, int|string> $filters */
    public function __construct(public int $companyId, public string $mode, public ?int $reconciliationId = null, public array $filters = []) {}
}
