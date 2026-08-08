<?php

namespace Modules\Organization\Application\Queries\GetOrganizationUnitList;

final readonly class GetOrganizationUnitListQuery
{
    /** @param array<string,mixed> $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
