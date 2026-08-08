<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class EmployeeSalaryComponentAssignmentPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'payroll.assignment.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.assignment.view', $c, 'employee_salary_component_assignments', $id);
    }

    public function assign(User $u, int $c, int $employee, int $component): bool
    {
        return $this->employee($u, 'payroll.assignment.manage', $c, $employee, 'payroll.assignment.manage') && $this->record($u, 'payroll.assignment.manage', $c, 'salary_components', $component);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.assignment.manage', $c, 'employee_salary_component_assignments', $id) && $this->status('employee_salary_component_assignments', $id) === 'active';
    }

    public function end(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }
}
