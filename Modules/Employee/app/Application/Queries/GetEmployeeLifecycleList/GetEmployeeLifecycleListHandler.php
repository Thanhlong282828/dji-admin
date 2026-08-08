<?php

namespace Modules\Employee\Application\Queries\GetEmployeeLifecycleList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Employee\Domain\Repositories\EmployeeLifecycleReadRepositoryInterface;

final readonly class GetEmployeeLifecycleListHandler
{
    public function __construct(private EmployeeLifecycleReadRepositoryInterface $repository) {}

    /** @return LengthAwarePaginator<int,array<string,mixed>> */
    public function handle(GetEmployeeLifecycleListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query->companyId, $query->search, $query->state, $query->perPage);
    }
}
