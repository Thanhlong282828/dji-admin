<?php

namespace Modules\Employee\Application\Queries\GetEmployeeList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Employee\Domain\Repositories\EmployeeReadRepositoryInterface;

final readonly class GetEmployeeListHandler
{
    public function __construct(private EmployeeReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function handle(GetEmployeeListQuery $q): LengthAwarePaginator
    {
        return $this->repo->paginate($q->companyId, $q->filters);
    }
}
