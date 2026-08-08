<?php

namespace Modules\Performance\Application\Commands\RefreshAnalyticsProjection;

use Modules\Performance\Domain\Services\WorkforceAnalyticsService;

final readonly class RefreshAnalyticsProjectionHandler
{
    public function __construct(private WorkforceAnalyticsService $service) {}

    public function handle(RefreshAnalyticsProjectionCommand $c): int
    {
        return $this->service->snapshot($c->companyId, $c->actorId, $c->key, $c->date);
    }
}
