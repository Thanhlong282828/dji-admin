<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class EmployeeUserLinkPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function link(User $user, int $companyId): bool
    {
        return $user->can('employee-user.link') && $this->tenant->canAccess($user, $companyId);
    }

    public function unlink(User $user, int $companyId): bool
    {
        return $user->can('employee-user.unlink') && $this->tenant->canAccess($user, $companyId);
    }

    public function invite(User $user, int $companyId): bool
    {
        return $user->can('employee-user.invite') && $this->tenant->canAccess($user, $companyId);
    }
}
