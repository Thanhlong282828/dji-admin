<?php

namespace Modules\Integration\Application\Commands;

final readonly class ActivateIntegrationConnectionCommand
{
    public function __construct(public int $companyId, public int $connectionId, public int $actorId) {}
}
