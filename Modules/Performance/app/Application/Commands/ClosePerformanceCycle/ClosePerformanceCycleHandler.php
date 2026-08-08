<?php

namespace Modules\Performance\Application\Commands\ClosePerformanceCycle;

use Modules\Performance\Domain\Services\PerformanceCycleService;

final readonly class ClosePerformanceCycleHandler
{
    public function __construct(private PerformanceCycleService $service) {}

    public function handle(ClosePerformanceCycleCommand $c): int
    {
        return $this->service->close($c->data);
    }
}
