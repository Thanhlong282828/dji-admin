<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class AttendancePeriodPolicy extends AbstractWorkforcePolicy
{
    public function viewReadiness(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'attendance.lock', $c, 'payroll_periods', $id);
    }

    public function lock(User $u, int $c, int $id): bool
    {
        return $this->viewReadiness($u, $c, $id) && $this->status('payroll_periods', $id) !== 'locked';
    }
}
