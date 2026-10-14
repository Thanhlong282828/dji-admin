<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class AcceptMaintenanceWorkOrderCommand
{
    public function __construct(public int $companyId, public int $workOrderId, public int $actorId, public string $note, public string $idempotencyKey) {}
}
