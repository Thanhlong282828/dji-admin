<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class AssignMaintenanceWorkOrderCommand
{
    public function __construct(public int $companyId, public int $orderId, public int $employeeId, public int $actorId) {}
}
