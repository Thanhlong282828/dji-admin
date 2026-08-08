<?php

namespace Modules\Employee\Application\Queries\GetEmployeeLifecycleList;

final readonly class GetEmployeeLifecycleListQuery
{
    public function __construct(public int $companyId, public ?string $search, public ?string $state, public int $perPage = 15) {}
}
