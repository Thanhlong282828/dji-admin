<?php

namespace Modules\Integration\Application\Commands;

final readonly class QueueGovernmentFilingIntegrationCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $connectionId, public int $actorId) {}
}
