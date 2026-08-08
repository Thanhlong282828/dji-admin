<?php

namespace Modules\Performance\Application\Commands\AssignCareerPlan;

use Modules\Performance\Domain\Services\CareerPathService;

final readonly class AssignCareerPlanHandler
{
    public function __construct(private CareerPathService $service) {}

    public function handle(AssignCareerPlanCommand $c): int
    {
        return $this->service->assign($c->data);
    }
}
