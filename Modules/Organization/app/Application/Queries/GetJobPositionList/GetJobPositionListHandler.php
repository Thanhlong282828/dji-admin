<?php

namespace Modules\Organization\Application\Queries\GetJobPositionList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetJobPositionListHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function handle(GetJobPositionListQuery $q): LengthAwarePaginator
    {
        return $this->repo->positions($q->companyId, $q->filters);
    }
}
