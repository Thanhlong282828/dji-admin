<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class EmployeeOvertimePolicyAssignmentPolicy extends AbstractWorkforcePolicy
{
    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.manage-policy', $c, 'employee_overtime_policy_assignments', $id);
    }

    public function assign(User $u, int $c, int $employee, int $policy): bool
    {
        return $this->employee($u, 'overtime.manage-policy', $c, $employee, 'overtime.manage-policy') && $this->record($u, 'overtime.manage-policy', $c, 'overtime_policies', $policy) && $this->status('overtime_policies', $policy) === 'active';
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
