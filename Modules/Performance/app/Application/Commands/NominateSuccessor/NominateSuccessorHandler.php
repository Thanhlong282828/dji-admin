<?php

namespace Modules\Performance\Application\Commands\NominateSuccessor;

use Modules\Performance\Domain\Services\SuccessionPlanningService;

final readonly class NominateSuccessorHandler
{
    public function __construct(private SuccessionPlanningService $service) {}

    public function handle(NominateSuccessorCommand $c): int
    {
        return $this->service->nominate($c->data);
    }
}
