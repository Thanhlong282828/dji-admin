<?php

namespace Modules\Organization\Application\Queries\GetOrganizationHeadcountDashboard;

final readonly class GetOrganizationHeadcountDashboardQuery
{
    /** @param array<string,mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
