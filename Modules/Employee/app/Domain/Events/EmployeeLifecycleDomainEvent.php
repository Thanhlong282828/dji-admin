<?php

namespace Modules\Employee\Domain\Events;

abstract readonly class EmployeeLifecycleDomainEvent
{
    public function __construct(public int $companyId, public int $employeeId, public int $lifecycleEventId) {}
}
