<?php

namespace Modules\Performance\Application\Queries\GetPerformanceDashboard;

use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;

final readonly class GetPerformanceDashboardHandler
{
    public function __construct(private PerformanceReadRepositoryInterface $repo) {}

    /** @return array{cycles: list<array<string, mixed>>, employees: list<array<string, mixed>>, stats: array<string, int|float>} */
    public function handle(GetPerformanceDashboardQuery $q): array
    {
        return $this->repo->dashboard($q->companyId);
    }
}
