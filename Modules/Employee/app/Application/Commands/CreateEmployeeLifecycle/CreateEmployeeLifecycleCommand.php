<?php

namespace Modules\Employee\Application\Commands\CreateEmployeeLifecycle;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class CreateEmployeeLifecycleCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
