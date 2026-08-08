<?php

namespace Modules\Integration\Application\Commands;

use Modules\Integration\Domain\Services\EnterpriseDomainIntegrationBridge;

final readonly class QueueErpExportIntegrationHandler
{
    public function __construct(private EnterpriseDomainIntegrationBridge $bridge) {}

    public function handle(QueueErpExportIntegrationCommand $command): int
    {
        return $this->bridge->queueErpExport($command->companyId, $command->erpExportId, $command->connectionId, $command->actorId);
    }
}
