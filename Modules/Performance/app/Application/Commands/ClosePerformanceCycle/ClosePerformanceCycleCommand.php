<?php

namespace Modules\Performance\Application\Commands\ClosePerformanceCycle;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class ClosePerformanceCycleCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
