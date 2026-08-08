<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class DataConsentPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function grant(User $user, int $companyId): bool
    {
        return $user->can('data-consent.grant') && $this->tenant->canAccess($user, $companyId);
    }

    public function withdraw(User $user, int $companyId): bool
    {
        return $user->can('data-consent.withdraw') && $this->tenant->canAccess($user, $companyId);
    }
}
