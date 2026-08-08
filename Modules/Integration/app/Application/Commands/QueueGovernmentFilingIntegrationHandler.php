<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\EnterpriseDomainIntegrationBridge;

final readonly class QueueGovernmentFilingIntegrationHandler
{
    public function __construct(private EnterpriseDomainIntegrationBridge $bridge) {}

    public function handle(QueueGovernmentFilingIntegrationCommand $command): int
    {
        return $this->bridge->queueGovernmentFiling($command->companyId, $command->filingId, $command->connectionId, $command->actorId);
    }
}
