<?php

namespace Modules\Company\Domain\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Company\Domain\Models\Company;

interface CompanyReadRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Company> */
    public function paginate(?string $search, ?string $status, int $perPage, int $page, string $sort, string $direction, bool $withTrashed = false): LengthAwarePaginator;

    public function findOrFail(int $id, bool $withTrashed = false): Company;

    /** @return array{total:int,active:int,inactive:int} */
    public function dashboardStats(): array;
}
