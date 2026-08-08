<?php

namespace Modules\Organization\Application\Queries\GetCostCenterBudgetSummary;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetCostCenterBudgetSummaryHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetCostCenterBudgetSummaryQuery $q): array
    {
        return $this->repo->costCenterBudget($q->companyId);
    }
}
