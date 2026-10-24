<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class SubmitMaintenanceRequestCommand
{
    public function __construct(public int $companyId, public int $requestId, public int $actorId) {}
}
