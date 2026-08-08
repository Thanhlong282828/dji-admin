<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class SalaryComponentPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'payroll.component.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.component.view', $c, 'salary_components', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'payroll.component.manage', $c);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.component.manage', $c, 'salary_components', $id) && $this->status('salary_components', $id) !== 'archived';
    }

    public function activate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id) && $this->status('salary_components', $id) === 'inactive';
    }

    public function deactivate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id) && $this->status('salary_components', $id) === 'active';
    }

    public function archive(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function assign(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.assignment.manage', $c, 'salary_components', $id) && $this->status('salary_components', $id) === 'active';
    }
}
