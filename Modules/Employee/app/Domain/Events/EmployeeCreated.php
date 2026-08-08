<?php

namespace Modules\Employee\Domain\Events;

final readonly class EmployeeCreated extends EmployeeChanged
{
    public function broadcastAs(): string
    {
        return 'employee.created';
    }
}
