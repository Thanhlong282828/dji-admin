<?php

namespace Modules\Company\Application\Queries\ListCompanies;

final readonly class ListCompaniesQuery
{
    public function __construct(public ?string $search = null, public int $perPage = 15) {}
}
