<?php

namespace Modules\Employee\Domain\Events;

final readonly class EmployeeDeleted extends EmployeeChanged
{
    public function broadcastAs(): string
    {
        return 'employee.deleted';
    }
}
