<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class ShiftPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'shift.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'shift.view', $c, 'work_shifts', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'shift.manage', $c);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'shift.manage', $c, 'work_shifts', $id) && $this->status('work_shifts', $id) !== 'archived';
    }

    public function activate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function deactivate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function archive(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function manageBreakRules(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function export(User $u, int $c): bool
    {
        return $this->viewAny($u, $c);
    }
}
