<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ServiceAcceptanceService;
use Modules\Procurement\Application\Services\ServiceAcceptanceWorkflowOrchestrator;

final readonly class SubmitServiceAcceptanceHandler
{
    public function __construct(private ServiceAcceptanceService $service, private ServiceAcceptanceWorkflowOrchestrator $workflow) {}

    public function handle(SubmitServiceAcceptanceCommand $command): int
    {
        return $this->workflow->submit($command->companyId, $command->acceptanceId, $command->actor, $this->service);
    }
}
