<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class LeavePolicyManagementPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'leave.manage-policy', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'leave.manage-policy', $c, 'leave_policies', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'leave.manage-policy', $c);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'leave.manage-policy', $c, 'leave_policies', $id);
    }

    public function activate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function deactivate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id) && $this->status('leave_policies', $id) === 'active';
    }

    public function assign(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'leave.manage-policy', $c, 'leave_policies', $id) && $this->status('leave_policies', $id) === 'active';
    }
}
