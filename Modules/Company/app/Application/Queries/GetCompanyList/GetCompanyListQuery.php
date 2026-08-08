<?php

namespace Modules\Company\Application\Queries\GetCompanyList;

final readonly class GetCompanyListQuery
{
    public function __construct(public ?string $search = null, public ?string $status = null, public int $perPage = 15, public int $page = 1, public string $sort = 'created_at', public string $direction = 'desc', public bool $withTrashed = false) {}
}
