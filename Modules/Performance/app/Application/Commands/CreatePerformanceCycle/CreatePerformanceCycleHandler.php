<?php

namespace Modules\Performance\Application\Commands\CreatePerformanceCycle;

use Modules\Performance\Domain\Services\PerformanceCycleService;

final readonly class CreatePerformanceCycleHandler
{
    public function __construct(private PerformanceCycleService $service) {}

    public function handle(CreatePerformanceCycleCommand $c): int
    {
        return $this->service->create($c->data);
    }
}
