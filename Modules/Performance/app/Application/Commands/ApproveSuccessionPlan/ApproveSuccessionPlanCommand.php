<?php

namespace Modules\Performance\Application\Commands\ApproveSuccessionPlan;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class ApproveSuccessionPlanCommand
{
    public function __construct(public CareerActionData $data) {}
}
