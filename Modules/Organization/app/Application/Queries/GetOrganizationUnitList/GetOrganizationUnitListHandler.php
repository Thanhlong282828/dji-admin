<?php

namespace Modules\Organization\Application\Queries\GetOrganizationUnitList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetOrganizationUnitListHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function handle(GetOrganizationUnitListQuery $q): LengthAwarePaginator
    {
        return $this->repo->units($q->companyId, $q->filters);
    }
}
