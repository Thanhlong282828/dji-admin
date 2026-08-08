<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class EmployeeAssignmentPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function view(User $user, int $companyId): bool
    {
        return $user->can('employee-assignment.view') && $this->tenant->canAccess($user, $companyId);
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->can('employee-assignment.create') && $this->tenant->canAccess($user, $companyId);
    }

    public function update(User $user, int $companyId): bool
    {
        return $user->can('employee-assignment.update') && $this->tenant->canAccess($user, $companyId);
    }

    public function end(User $user, int $companyId): bool
    {
        return $user->can('employee-assignment.end') && $this->tenant->canAccess($user, $companyId);
    }

    public function transfer(User $user, int $companyId): bool
    {
        return $user->can('employee-assignment.transfer') && $this->tenant->canAccess($user, $companyId);
    }

    public function promote(User $user, int $companyId): bool
    {
        return $user->can('employee-assignment.promote') && $this->tenant->canAccess($user, $companyId);
    }
}
