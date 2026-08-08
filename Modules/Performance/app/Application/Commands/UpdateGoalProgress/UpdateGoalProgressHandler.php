<?php

namespace Modules\Performance\Application\Commands\UpdateGoalProgress;

use Modules\Performance\Domain\Services\GoalManagementService;

final readonly class UpdateGoalProgressHandler
{
    public function __construct(private GoalManagementService $service) {}

    public function handle(UpdateGoalProgressCommand $c): int
    {
        return $this->service->progress($c->data);
    }
}
