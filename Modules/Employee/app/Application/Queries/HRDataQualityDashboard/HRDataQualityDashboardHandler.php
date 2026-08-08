<?php

namespace Modules\Employee\Application\Queries\HRDataQualityDashboard;

use Modules\Employee\Application\DTOs\HRGovernanceDashboardViewModel;
use Modules\Employee\Domain\Repositories\HRGovernanceReadRepositoryInterface;

final readonly class HRDataQualityDashboardHandler
{
    public function __construct(private HRGovernanceReadRepositoryInterface $repository) {}

    public function handle(HRDataQualityDashboardQuery $query): HRGovernanceDashboardViewModel
    {
        return $this->repository->dashboard($query->companyId);
    }
}
