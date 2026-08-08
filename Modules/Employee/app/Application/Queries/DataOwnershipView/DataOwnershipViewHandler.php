<?php

namespace Modules\Employee\Application\Queries\DataOwnershipView;

use Modules\Employee\Application\DTOs\HRGovernanceDashboardViewModel;
use Modules\Employee\Domain\Repositories\HRGovernanceReadRepositoryInterface;

final readonly class DataOwnershipViewHandler
{
    public function __construct(private HRGovernanceReadRepositoryInterface $repository) {}

    public function handle(DataOwnershipViewQuery $query): HRGovernanceDashboardViewModel
    {
        return $this->repository->dashboard($query->companyId);
    }
}
