<?php

namespace Modules\Organization\Application\Queries\GetCriticalPositionList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetCriticalPositionListHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function handle(GetCriticalPositionListQuery $q): LengthAwarePaginator
    {
        return $this->repo->positions($q->companyId, ['critical_only' => true]);
    }
}
