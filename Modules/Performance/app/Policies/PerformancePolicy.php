<?php

namespace Modules\Performance\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class PerformancePolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function allows(User $user, int $companyId, string $permission): bool
    {
        return $user->can($permission) && $this->tenant->canAccess($user, $companyId);
    }
}
