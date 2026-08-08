<?php

namespace Modules\Iam\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Iam\Domain\Contracts\AccessProvisioningAdapterInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class SpatieAccessProvisioningAdapter implements AccessProvisioningAdapterInterface
{
    public function provision(int $companyId, int $userId, string $type, int $entitlementId): void
    {
        abort_unless(DB::table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $userId])->exists(), 404);
        $user = User::query()->findOrFail($userId);
        $type === 'role' ? $user->assignRole(Role::findById($entitlementId)) : $user->givePermissionTo(Permission::findById($entitlementId));
    }

    public function revoke(int $companyId, int $userId, string $type, int $entitlementId): void
    {
        abort_unless(DB::table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $userId])->exists(), 404);
        $user = User::query()->findOrFail($userId);
        $type === 'role' ? $user->removeRole(Role::findById($entitlementId)) : $user->revokePermissionTo(Permission::findById($entitlementId));
    }
}
