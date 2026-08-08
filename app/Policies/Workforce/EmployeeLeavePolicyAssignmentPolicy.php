<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class EmployeeLeavePolicyAssignmentPolicy extends AbstractWorkforcePolicy
{
    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'leave.manage-policy', $c, 'employee_leave_policy_assignments', $id);
    }

    public function assign(User $u, int $c, int $employee, int $policy): bool
    {
        return $this->employee($u, 'leave.manage-policy', $c, $employee, 'leave.manage-policy') && $this->record($u, 'leave.manage-policy', $c, 'leave_policies', $policy) && $this->status('leave_policies', $policy) === 'active';
    }

    public function change(User $u, int $c, int $id): bool
    {
        return $this->view($u, $c, $id);
    }

    public function end(User $u, int $c, int $id): bool
    {
        return $this->view($u, $c, $id);
    }
}
