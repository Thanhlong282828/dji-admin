<?php

namespace Modules\Company\Policies;

use App\Models\User;

final class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('company.view');
    }

    public function view(User $user): bool
    {
        return $user->can('company.view');
    }

    public function create(User $user): bool
    {
        return $user->can('company.create');
    }

    public function update(User $user): bool
    {
        return $user->can('company.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('company.delete');
    }

    public function restore(User $user): bool
    {
        return $user->can('company.restore');
    }

    public function changeStatus(User $user): bool
    {
        return $user->can('company.change-status');
    }
}
