<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class OvertimePolicyManagementPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'overtime.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.view', $c, 'overtime_policies', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'overtime.manage-policy', $c);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.manage-policy', $c, 'overtime_policies', $id);
    }

    public function activate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function deactivate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id) && $this->status('overtime_policies', $id) === 'active';
    }

    public function assign(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.manage-policy', $c, 'overtime_policies', $id) && $this->status('overtime_policies', $id) === 'active';
    }
}
