<?php

namespace Modules\Employee\Application\Commands\DemoteEmployee;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class DemoteEmployeeCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
