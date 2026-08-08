<?php

namespace Modules\Employee\Application\Queries\ConsentAuditView;

use Modules\Employee\Application\DTOs\HRGovernanceDashboardViewModel;
use Modules\Employee\Domain\Repositories\HRGovernanceReadRepositoryInterface;

final readonly class ConsentAuditViewHandler
{
    public function __construct(private HRGovernanceReadRepositoryInterface $repository) {}

    public function handle(ConsentAuditViewQuery $query): HRGovernanceDashboardViewModel
    {
        return $this->repository->dashboard($query->companyId);
    }
}
