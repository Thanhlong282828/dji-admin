<?php

namespace Modules\Employee\Application\Commands\TerminateEmployee;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class TerminateEmployeeCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
