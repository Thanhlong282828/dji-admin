<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class WorkforceAuthorizationPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function company(User $user, string $permission, int $companyId): bool
    {
        return $companyId > 0 && $user->can($permission) && $this->tenant->canAccess($user, $companyId);
    }

    public function employee(User $user, string $permission, int $companyId, int $employeeId, string $managementPermission): bool
    {
        if (! $this->company($user, $permission, $companyId) || $employeeId <= 0) {
            return false;
        }

        if ($user->can($managementPermission)) {
            if (! DB::table('employees')->where('id', $employeeId)->where('company_id', $companyId)->exists()) {
                abort(404);
            }

            return true;
        }

        return DB::table('employees')->where('id', $employeeId)->where('company_id', $companyId)->where('user_id', $user->id)->exists();
    }
}
