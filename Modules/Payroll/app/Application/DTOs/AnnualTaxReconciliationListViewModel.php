<?php

namespace Modules\Payroll\Application\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class AnnualTaxReconciliationListViewModel
{
    /**
     * @param  list<array<string,mixed>>  $items
     * @param  LengthAwarePaginator<int,object>  $page
     * @param  array{tax_year:string,status:string,result_type:string}  $filters
     */
    public function __construct(public array $items, public LengthAwarePaginator $page, public array $filters) {}

    /** @return array{rows:list<array<string,mixed>>,page:LengthAwarePaginator<int,object>,filters:array{tax_year:string,status:string,result_type:string}} */
    public function toViewData(): array
    {
        return ['rows' => $this->items, 'page' => $this->page, 'filters' => $this->filters];
    }
}
