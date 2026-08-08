<?php

namespace Modules\Organization\Application\Queries\GetCostCenterBudgetSummary;

final readonly class GetCostCenterBudgetSummaryQuery
{
    public function __construct(public int $companyId) {}
}
