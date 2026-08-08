<?php

namespace Modules\Performance\Application\Commands\AssessSuccessor;

use Modules\Performance\Domain\Services\SuccessionPlanningService;

final readonly class AssessSuccessorHandler
{
    public function __construct(private SuccessionPlanningService $service) {}

    public function handle(AssessSuccessorCommand $c): int
    {
        return $this->service->assess($c->data);
    }
}
