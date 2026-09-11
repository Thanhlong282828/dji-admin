<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class AddMaintenancePartCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $orderId, public array $data, public int $actorId) {}
}
