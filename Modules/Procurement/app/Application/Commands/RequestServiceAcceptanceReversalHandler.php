<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ServiceAcceptanceService;
use Modules\Procurement\Application\Services\ServiceAcceptanceWorkflowOrchestrator;

final readonly class RequestServiceAcceptanceReversalHandler
{
    public function __construct(private ServiceAcceptanceService $service, private ServiceAcceptanceWorkflowOrchestrator $workflow) {}

    public function handle(RequestServiceAcceptanceReversalCommand $command): int
    {
        $result = $this->service->requestReversal($command->companyId, $command->acceptanceId, $command->targetPeriodId, $command->reason, $command->idempotencyKey, $command->actor);
        $this->workflow->reversalRequested($command->companyId, $command->acceptanceId, $command->actor);

        return $result;
    }
}
