<?php

namespace Modules\Performance\Application\Commands\CreateSuccessionPlan;

use Modules\Performance\Domain\Services\SuccessionPlanningService;

final readonly class CreateSuccessionPlanHandler
{
    public function __construct(private SuccessionPlanningService $service) {}

    public function handle(CreateSuccessionPlanCommand $c): int
    {
        return $this->service->create($c->data);
    }
}
