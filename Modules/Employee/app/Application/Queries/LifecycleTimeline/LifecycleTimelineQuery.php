<?php

namespace Modules\Employee\Application\Queries\LifecycleTimeline;

final readonly class LifecycleTimelineQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
