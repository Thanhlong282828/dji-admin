<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxHistory;

final readonly class GetAnnualTaxHistoryQuery
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $page = 1) {}
}
