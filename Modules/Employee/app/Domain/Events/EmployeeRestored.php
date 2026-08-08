<?php

namespace Modules\Employee\Domain\Events;

final readonly class EmployeeRestored extends EmployeeChanged
{
    public function broadcastAs(): string
    {
        return 'employee.restored';
    }
}
