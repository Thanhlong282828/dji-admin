<?php

namespace Modules\Performance\Application\Queries\GoalTimeline;

final readonly class GoalTimelineQuery
{
    public function __construct(public int $companyId, public int $goalId) {}
}
