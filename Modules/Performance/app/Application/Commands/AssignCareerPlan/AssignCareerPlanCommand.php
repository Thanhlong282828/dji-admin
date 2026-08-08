<?php

namespace Modules\Performance\Application\Commands\AssignCareerPlan;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class AssignCareerPlanCommand
{
    public function __construct(public CareerActionData $data) {}
}
