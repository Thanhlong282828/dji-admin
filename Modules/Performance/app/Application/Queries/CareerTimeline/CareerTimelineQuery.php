<?php

namespace Modules\Performance\Application\Queries\CareerTimeline;

final readonly class CareerTimelineQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
