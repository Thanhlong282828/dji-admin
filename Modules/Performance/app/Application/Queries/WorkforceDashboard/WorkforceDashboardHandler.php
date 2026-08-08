<?php

namespace Modules\Performance\Application\Queries\WorkforceDashboard;

use Modules\Performance\Application\DTOs\WorkforceAnalyticsViewModel;
use Modules\Performance\Domain\Services\WorkforceAnalyticsService;

final readonly class WorkforceDashboardHandler
{
    public function __construct(private WorkforceAnalyticsService $service) {}

    public function handle(WorkforceDashboardQuery $q): WorkforceAnalyticsViewModel
    {
        return $this->service->calculate($q->companyId);
    }
}
