<?php

namespace Modules\Company\Application\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class CompanyListViewModel
{
    /** @param LengthAwarePaginator<int,CompanyViewModel> $companies */
    public function __construct(public LengthAwarePaginator $companies) {}
}
