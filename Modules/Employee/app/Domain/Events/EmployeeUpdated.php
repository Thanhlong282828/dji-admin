<?php

namespace Modules\Employee\Domain\Events;

final readonly class EmployeeUpdated extends EmployeeChanged
{
    public function broadcastAs(): string
    {
        return 'employee.updated';
    }
}
