<?php

namespace Modules\Performance\Application\Commands\GenerateWorkforceSnapshot;

use Modules\Performance\Domain\Services\WorkforceAnalyticsService;

final readonly class GenerateWorkforceSnapshotHandler
{
    public function __construct(private WorkforceAnalyticsService $service) {}

    public function handle(GenerateWorkforceSnapshotCommand $c): int
    {
        return $this->service->snapshot($c->companyId, $c->actorId, $c->key, $c->date);
    }
}
