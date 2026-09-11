<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class IssueMaintenancePartCommand
{
    public function __construct(public int $companyId, public int $orderId, public int $partId, public int $actorId) {}
}
