<?php

namespace Modules\Employee\Application\Queries\DocumentGovernanceView;

use Modules\Employee\Application\DTOs\HRGovernanceDashboardViewModel;
use Modules\Employee\Domain\Repositories\HRGovernanceReadRepositoryInterface;

final readonly class DocumentGovernanceViewHandler
{
    public function __construct(private HRGovernanceReadRepositoryInterface $repository) {}

    public function handle(DocumentGovernanceViewQuery $query): HRGovernanceDashboardViewModel
    {
        return $this->repository->dashboard($query->companyId);
    }
}
