<?php

namespace App\Support\Commerce;

use App\Models\User;
use Illuminate\Database\DatabaseManager;

final readonly class BranchAccessService
{
    public function __construct(private DatabaseManager $db) {}

    public function company(User $user, int $companyId): bool
    {
        return $user->can('organization.tenant-all') || $this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $user->id])->exists();
    }

    public function branch(User $user, int $companyId, int $branchId): bool
    {
        if (! $this->company($user, $companyId)) {
            return false;
        }
        if ($user->can('organization.tenant-all')) {
            return $this->db->table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->exists();
        }

        return $this->db->table('branches as b')->join('branch_user_access as a', 'a.branch_id', '=', 'b.id')->where('b.company_id', $companyId)->where('b.id', $branchId)->where('a.user_id', $user->id)->exists();
    }
}
