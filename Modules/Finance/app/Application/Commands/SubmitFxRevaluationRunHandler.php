<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FxRevaluationService;

final readonly class SubmitFxRevaluationRunHandler
{
    public function __construct(private FxRevaluationService $service) {}

    public function handle(SubmitFxRevaluationRunCommand $command): void
    {
        $this->service->transition($command->companyId, $command->runId, 'submit', $command->actor);
    }
}
