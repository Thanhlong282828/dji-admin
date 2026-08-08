<?php

namespace Modules\Organization\Application\Queries\GetOrganizationUnitDetail;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetOrganizationUnitDetailHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetOrganizationUnitDetailQuery $q): array
    {
        return $this->repo->unit($q->companyId, $q->id);
    }
}
