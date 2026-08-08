<?php

namespace Modules\Employee\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class EmployeeDocumentPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function upload(User $user, int $companyId): bool
    {
        return $user->can('employee-document.upload') && $this->tenant->canAccess($user, $companyId);
    }

    public function download(User $user, int $companyId): bool
    {
        return $user->can('employee-document.download') && $this->tenant->canAccess($user, $companyId);
    }

    public function archive(User $user, int $companyId): bool
    {
        return $user->can('employee-document.archive') && $this->tenant->canAccess($user, $companyId);
    }
}
