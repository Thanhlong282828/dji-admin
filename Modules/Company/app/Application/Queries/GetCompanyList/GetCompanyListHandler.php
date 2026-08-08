<?php

namespace Modules\Company\Application\Queries\GetCompanyList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Company\Application\DTOs\CompanyListViewModel;
use Modules\Company\Application\DTOs\CompanyViewModel;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;

final readonly class GetCompanyListHandler
{
    public function __construct(private CompanyReadRepositoryInterface $companies) {}

    public function handle(GetCompanyListQuery $query): CompanyListViewModel
    {
        $page = $this->companies->paginate($query->search, $query->status, min(max($query->perPage, 5), 100), max($query->page, 1), $query->sort, $query->direction, $query->withTrashed);
        $items = $page->getCollection()->map(fn (Company $company): CompanyViewModel => CompanyViewModel::fromModel($company));
        /** @var LengthAwarePaginator<int, CompanyViewModel> $viewPage */
        $viewPage = new LengthAwarePaginator($items, $page->total(), $page->perPage(), $page->currentPage(), ['path' => $page->path(), 'query' => request()->query()]);

        return new CompanyListViewModel($viewPage);
    }
}
