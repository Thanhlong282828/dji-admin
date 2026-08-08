<?php

namespace Modules\Organization\Application\Queries\GetOrganizationBreadcrumb;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetOrganizationBreadcrumbHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return list<array<string, mixed>> */
    public function handle(GetOrganizationBreadcrumbQuery $q): array
    {
        return $this->repo->breadcrumb($q->companyId, $q->id);
    }
}
