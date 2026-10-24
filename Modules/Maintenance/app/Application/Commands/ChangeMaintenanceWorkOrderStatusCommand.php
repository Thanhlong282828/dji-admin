<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class ChangeMaintenanceWorkOrderStatusCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $orderId, public string $status, public int $actorId, public array $data = []) {}
}
