<?php

namespace Modules\Organization\Application\Queries\GetOrganizationTree;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetOrganizationTreeHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return list<array<string, mixed>> */
    public function handle(GetOrganizationTreeQuery $q): array
    {
        return $this->repo->tree($q->companyId, $q->filters);
    }
}
