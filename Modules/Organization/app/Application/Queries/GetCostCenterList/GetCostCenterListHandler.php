<?php

namespace Modules\Organization\Application\Queries\GetCostCenterList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetCostCenterListHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function handle(GetCostCenterListQuery $q): LengthAwarePaginator
    {
        return $this->repo->costCenters($q->companyId, $q->filters);
    }
}
