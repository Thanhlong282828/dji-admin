<?php

namespace Modules\Iam\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class IamTenantAccessService
{
    public function authorize(User $user, int $companyId): void
    {
        abort_unless($user->can('organization.tenant-all') || DB::table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $user->id])->exists(), 404);
    }
}
