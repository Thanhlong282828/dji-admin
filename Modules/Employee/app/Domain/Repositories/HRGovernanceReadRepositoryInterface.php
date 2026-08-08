<?php

namespace Modules\Employee\Domain\Repositories;

use Modules\Employee\Application\DTOs\HRGovernanceDashboardViewModel;

interface HRGovernanceReadRepositoryInterface
{
    public function dashboard(int $companyId): HRGovernanceDashboardViewModel;
}
