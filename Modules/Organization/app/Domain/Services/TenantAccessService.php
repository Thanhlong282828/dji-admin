<?php

namespace Modules\Organization\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TenantAccessService
{
    public function canAccess(User $user, int $companyId): bool
    {
        return $user->can('organization.tenant-all') || DB::table('company_user_access')->where('user_id', $user->id)->where('company_id', $companyId)->exists();
    }
}
