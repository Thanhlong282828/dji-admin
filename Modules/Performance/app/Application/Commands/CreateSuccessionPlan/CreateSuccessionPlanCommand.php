<?php

namespace Modules\Performance\Application\Commands\CreateSuccessionPlan;

use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class CreateSuccessionPlanCommand
{
    public function __construct(public CareerActionData $data) {}
}
