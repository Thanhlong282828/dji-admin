<?php

namespace Modules\Organization\Application\Queries\GetCostCenterList;

final readonly class GetCostCenterListQuery
{
    /** @param array<string,mixed> $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
