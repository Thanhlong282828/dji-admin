<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class EmployeeCredentialPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function view(User $user, int $companyId): bool
    {
        return $user->can('employee-credential.view') && $this->tenant->canAccess($user, $companyId);
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->can('employee-credential.create') && $this->tenant->canAccess($user, $companyId);
    }

    public function update(User $user, int $companyId): bool
    {
        return $user->can('employee-credential.update') && $this->tenant->canAccess($user, $companyId);
    }

    public function archive(User $user, int $companyId): bool
    {
        return $user->can('employee-credential.archive') && $this->tenant->canAccess($user, $companyId);
    }

    public function restore(User $user, int $companyId): bool
    {
        return $user->can('employee-credential.restore') && $this->tenant->canAccess($user, $companyId);
    }
}
