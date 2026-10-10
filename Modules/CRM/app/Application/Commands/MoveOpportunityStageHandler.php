<?php

namespace Modules\CRM\Application\Commands;

use Modules\CRM\Application\Services\CrmService;

final readonly class MoveOpportunityStageHandler
{
    public function __construct(private CrmService $service) {}

    public function handle(MoveOpportunityStageCommand $command): int
    {
        return $this->service->execute('opportunity-stage', $command->companyId, $command->actor, $command->data);
    }
}
