<?php

namespace Modules\Performance\Application\Commands\RebuildAnalyticsPeriod;

use Modules\Performance\Domain\Services\WorkforceAnalyticsService;

final readonly class RebuildAnalyticsPeriodHandler
{
    public function __construct(private WorkforceAnalyticsService $service) {}

    public function handle(RebuildAnalyticsPeriodCommand $c): int
    {
        return $this->service->snapshot($c->companyId, $c->actorId, $c->key, $c->date, true);
    }
}
