<?php

namespace Modules\Organization\Application\Queries\GetCostCenterDetail;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetCostCenterDetailHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetCostCenterDetailQuery $q): array
    {
        return $this->repo->costCenter($q->companyId, $q->id);
    }
}
