<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class EmployeePolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function view(User $user, int $companyId): bool
    {
        return $user->can('employee.view') && $this->tenant->canAccess($user, $companyId);
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->can('employee.create') && $this->tenant->canAccess($user, $companyId);
    }

    public function update(User $user, int $companyId): bool
    {
        return $user->can('employee.update') && $this->tenant->canAccess($user, $companyId);
    }

    public function delete(User $user, int $companyId): bool
    {
        return $user->can('employee.delete') && $this->tenant->canAccess($user, $companyId);
    }

    public function restore(User $user, int $companyId): bool
    {
        return $user->can('employee.restore') && $this->tenant->canAccess($user, $companyId);
    }
}
