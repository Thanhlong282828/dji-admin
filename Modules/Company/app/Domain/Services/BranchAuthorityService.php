<?php

namespace Modules\Company\Domain\Services;

use App\Models\User;

final class BranchAuthorityService
{
    private const LEADER_ROLES = ['Super Admin', 'System Admin', 'HR Admin', 'HR Manager', 'Manager'];

    public function canMutate(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'System Admin']);
    }

    public function canManageUsers(User $user): bool
    {
        return $this->canMutate($user);
    }

    public function canLead(User $user): bool
    {
        return $user->hasAnyRole(self::LEADER_ROLES);
    }

    /** @return list<string> */
    public function leaderRoles(): array
    {
        return self::LEADER_ROLES;
    }

    /** @return array<string,array{view:bool,create:bool,edit:bool,assign_leader:bool,manage_users:bool,role_management:string,scope:string}> */
    public function matrix(): array
    {
        return [
            'Super Admin' => ['view' => true, 'create' => true, 'edit' => true, 'assign_leader' => true, 'manage_users' => true, 'role_management' => 'YES', 'scope' => 'Platform'],
            'System Admin' => ['view' => true, 'create' => true, 'edit' => true, 'assign_leader' => true, 'manage_users' => true, 'role_management' => 'BOUNDED', 'scope' => 'Company access'],
            'HR Admin' => ['view' => true, 'create' => false, 'edit' => false, 'assign_leader' => false, 'manage_users' => false, 'role_management' => 'NO', 'scope' => 'Authorized company/branch read'],
            'HR Manager' => ['view' => true, 'create' => false, 'edit' => false, 'assign_leader' => false, 'manage_users' => false, 'role_management' => 'NO', 'scope' => 'Authorized company/branch read'],
            'Employee' => ['view' => false, 'create' => false, 'edit' => false, 'assign_leader' => false, 'manage_users' => false, 'role_management' => 'NO', 'scope' => 'No Branch Governance'],
            'Viewer' => ['view' => true, 'create' => false, 'edit' => false, 'assign_leader' => false, 'manage_users' => false, 'role_management' => 'NO', 'scope' => 'Authorized read'],
        ];
    }
}
