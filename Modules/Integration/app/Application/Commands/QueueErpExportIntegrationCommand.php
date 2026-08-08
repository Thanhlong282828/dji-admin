<?php

namespace Modules\Integration\Application\Commands;

final readonly class QueueErpExportIntegrationCommand
{
    public function __construct(public int $companyId, public int $erpExportId, public int $connectionId, public int $actorId) {}
}
