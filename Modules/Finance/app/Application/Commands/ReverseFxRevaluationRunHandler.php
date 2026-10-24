<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FxRevaluationService;

final readonly class ReverseFxRevaluationRunHandler
{
    public function __construct(private FxRevaluationService $service) {}

    public function handle(ReverseFxRevaluationRunCommand $command): int
    {
        return $this->service->reverse($command->companyId, $command->runId, $command->actor);
    }
}
