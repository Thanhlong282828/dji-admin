<?php

namespace Modules\Performance\Application\Commands\UpdateGoalProgress;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class UpdateGoalProgressCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
