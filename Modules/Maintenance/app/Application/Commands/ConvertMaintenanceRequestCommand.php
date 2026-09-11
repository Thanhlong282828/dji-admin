<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class ConvertMaintenanceRequestCommand
{
    public function __construct(public int $companyId, public int $requestId, public int $actorId) {}
}
