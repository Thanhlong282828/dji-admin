<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxSource;

final readonly class GetAnnualTaxSourceQuery
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $sourcesPage = 1, public int $componentsPage = 1, public int $issuesPage = 1) {}
}
