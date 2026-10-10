<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FxRevaluationService;

final readonly class PostFxRevaluationRunHandler
{
    public function __construct(private FxRevaluationService $service) {}

    public function handle(PostFxRevaluationRunCommand $command): ?int
    {
        return $this->service->post($command->companyId, $command->runId, $command->actor);
    }
}
