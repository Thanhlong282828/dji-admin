<?php

namespace Modules\Organization\Application\Queries\GetJobPositionDetail;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetJobPositionDetailHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetJobPositionDetailQuery $q): array
    {
        return $this->repo->position($q->companyId, $q->id);
    }
}
