<?php

namespace Modules\Performance\Application\Commands\CreatePerformanceCycle;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class CreatePerformanceCycleCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
