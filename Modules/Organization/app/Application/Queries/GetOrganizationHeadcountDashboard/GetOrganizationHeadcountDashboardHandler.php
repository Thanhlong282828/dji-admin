<?php

namespace Modules\Organization\Application\Queries\GetOrganizationHeadcountDashboard;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetOrganizationHeadcountDashboardHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetOrganizationHeadcountDashboardQuery $q): array
    {
        return $this->repo->dashboard($q->companyId, $q->filters);
    }
}
