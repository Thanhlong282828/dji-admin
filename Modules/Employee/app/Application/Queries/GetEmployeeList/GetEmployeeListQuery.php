<?php

namespace Modules\Employee\Application\Queries\GetEmployeeList;

final readonly class GetEmployeeListQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
