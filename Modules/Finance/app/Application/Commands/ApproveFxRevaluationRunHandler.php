<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FxRevaluationService;

final readonly class ApproveFxRevaluationRunHandler
{
    public function __construct(private FxRevaluationService $service) {}

    public function handle(ApproveFxRevaluationRunCommand $command): void
    {
        $this->service->transition($command->companyId, $command->runId, 'approve', $command->actor);
    }
}
