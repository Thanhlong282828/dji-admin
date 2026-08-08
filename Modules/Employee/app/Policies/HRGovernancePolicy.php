<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class HRGovernancePolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function allows(User $user, int $companyId, string $permission): bool
    {
        return $user->can($permission) && $this->tenant->canAccess($user, $companyId);
    }

    public function canAccessCompany(User $user, int $companyId): bool
    {
        return $this->tenant->canAccess($user, $companyId);
    }
}
