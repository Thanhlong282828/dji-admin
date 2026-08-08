<?php

namespace Modules\Performance\Application\Queries\CompetencyProfile;

use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;

final readonly class CompetencyProfileHandler
{
    public function __construct(private PerformanceReadRepositoryInterface $repo) {}

    /** @return list<array<string, mixed>> */
    public function handle(CompetencyProfileQuery $q): array
    {
        return $this->repo->competencyProfile($q->companyId, $q->employeeId);
    }
}
