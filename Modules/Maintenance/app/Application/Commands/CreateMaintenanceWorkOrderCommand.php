<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class CreateMaintenanceWorkOrderCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data, public int $actorId) {}
}
