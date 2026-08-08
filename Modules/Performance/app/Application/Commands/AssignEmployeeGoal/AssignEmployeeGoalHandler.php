<?php

namespace Modules\Performance\Application\Commands\AssignEmployeeGoal;

use Modules\Performance\Domain\Services\GoalManagementService;

final readonly class AssignEmployeeGoalHandler
{
    public function __construct(private GoalManagementService $service) {}

    public function handle(AssignEmployeeGoalCommand $c): int
    {
        return $this->service->assign($c->data);
    }
}
