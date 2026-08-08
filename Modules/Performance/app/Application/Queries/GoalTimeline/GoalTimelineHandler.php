<?php

namespace Modules\Performance\Application\Queries\GoalTimeline;

use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;

final readonly class GoalTimelineHandler
{
    public function __construct(private PerformanceReadRepositoryInterface $repo) {}

    /** @return list<array<string, mixed>> */
    public function handle(GoalTimelineQuery $q): array
    {
        return $this->repo->goalTimeline($q->companyId, $q->goalId);
    }
}
