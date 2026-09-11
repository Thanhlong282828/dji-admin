<?php

namespace Modules\Employee\Domain\Events;

final readonly class HrOperationsChanged
{
    public function __construct(public int $companyId, public int $employeeId, public string $entityType, public int $entityId, public string $action, public int $version, public ?int $actorId) {}
}
