<?php

namespace Modules\Performance\Application\Commands\ApproveSuccessionPlan;

use Modules\Performance\Domain\Services\SuccessionPlanningService;

final readonly class ApproveSuccessionPlanHandler
{
    public function __construct(private SuccessionPlanningService $service) {}

    public function handle(ApproveSuccessionPlanCommand $c): int
    {
        return $this->service->approve($c->data);
    }
}
