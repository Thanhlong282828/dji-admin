<?php

namespace Modules\Performance\Application\Commands\AssignEmployeeGoal;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class AssignEmployeeGoalCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
