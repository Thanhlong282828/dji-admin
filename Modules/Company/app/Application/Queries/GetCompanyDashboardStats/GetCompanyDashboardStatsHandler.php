<?php

namespace Modules\Company\Application\Queries\GetCompanyDashboardStats;

use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;

final readonly class GetCompanyDashboardStatsHandler
{
    public function __construct(private CompanyReadRepositoryInterface $companies) {}

    /** @return array{total:int,active:int,inactive:int} */
    public function handle(GetCompanyDashboardStatsQuery $query): array
    {
        return $this->companies->dashboardStats();
    }
}
