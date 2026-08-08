<?php

namespace Modules\Performance\Application\Queries\PerformanceHistory;

use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;

final readonly class PerformanceHistoryHandler
{
    public function __construct(private PerformanceReadRepositoryInterface $repo) {}

    /** @return list<array<string, mixed>> */
    public function handle(PerformanceHistoryQuery $q): array
    {
        return $this->repo->history($q->companyId, $q->employeeId);
    }
}
