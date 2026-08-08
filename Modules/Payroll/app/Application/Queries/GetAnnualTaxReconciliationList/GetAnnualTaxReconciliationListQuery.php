<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxReconciliationList;

final readonly class GetAnnualTaxReconciliationListQuery
{
    public function __construct(public int $companyId, public string $taxYear = '', public string $status = '', public string $resultType = '', public int $page = 1) {}
}
