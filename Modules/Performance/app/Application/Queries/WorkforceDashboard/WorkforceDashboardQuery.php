<?php

namespace Modules\Performance\Application\Queries\WorkforceDashboard;

use Modules\Performance\Application\DTOs\AnalyticsFilter;

final readonly class WorkforceDashboardQuery
{
    public function __construct(public int $companyId, public AnalyticsFilter $filter, public bool $salaryAllowed) {}
}
