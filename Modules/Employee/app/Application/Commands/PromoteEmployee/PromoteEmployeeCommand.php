<?php

namespace Modules\Employee\Application\Commands\PromoteEmployee;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class PromoteEmployeeCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
