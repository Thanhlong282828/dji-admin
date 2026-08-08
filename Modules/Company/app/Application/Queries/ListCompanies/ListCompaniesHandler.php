<?php

namespace Modules\Company\Application\Queries\ListCompanies;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;

final readonly class ListCompaniesHandler
{
    public function __construct(private CompanyReadRepositoryInterface $companies) {}

    /** @return LengthAwarePaginator<int, Company> */
    public function handle(ListCompaniesQuery $query): LengthAwarePaginator
    {
        return $this->companies->paginate($query->search, null, min($query->perPage, 100), 1, 'created_at', 'desc');
    }
}
