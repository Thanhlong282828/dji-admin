<?php

namespace Modules\Employee\Application\Commands\TransferLifecycleEmployee;

use Modules\Employee\Application\DTOs\LifecycleTransitionData;

final readonly class TransferEmployeeCommand
{
    public function __construct(public LifecycleTransitionData $data) {}
}
