<?php

namespace Modules\Payroll\Application\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class AnnualTaxHistoryViewModel
{
    /**
     * @param  list<array<string,mixed>>  $items
     * @param  LengthAwarePaginator<int,object>  $page
     */
    public function __construct(public array $items, public LengthAwarePaginator $page) {}

    /** @return array{history:list<array<string,mixed>>,history_page:LengthAwarePaginator<int,object>} */
    public function toViewData(): array
    {
        return ['history' => $this->items, 'history_page' => $this->page];
    }
}
