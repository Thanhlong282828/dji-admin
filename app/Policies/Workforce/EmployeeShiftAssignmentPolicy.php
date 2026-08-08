<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class EmployeeShiftAssignmentPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'shift.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'shift.view', $c, 'employee_shift_assignments', $id);
    }

    public function assign(User $u, int $c, int $employee, int $shift): bool
    {
        return $this->employee($u, 'shift.assign', $c, $employee, 'shift.assign') && $this->record($u, 'shift.assign', $c, 'work_shifts', $shift);
    }

    public function change(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'shift.assign', $c, 'employee_shift_assignments', $id);
    }

    public function end(User $u, int $c, int $id): bool
    {
        return $this->change($u, $c, $id);
    }

    public function export(User $u, int $c): bool
    {
        return $this->viewAny($u, $c);
    }
}
