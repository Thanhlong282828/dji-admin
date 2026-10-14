<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class ReturnMaintenancePartCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $orderId, public int $partId, public int $actorId, public array $data) {}
}
