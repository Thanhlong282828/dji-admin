<?php

namespace App\Support\Iam;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RoleHierarchyService
{
    /** @var array<string, int> */
    private const RANKS = ['Super Admin' => 100, 'System Admin' => 90, 'HR Admin' => 75, 'HR Manager' => 70, 'C&B' => 70, 'Finance' => 70, 'Manager' => 60, 'Recruiter' => 55, 'HRBP' => 55, 'L&D' => 50, 'Content Admin' => 50, 'Editor' => 40, 'Auditor' => 35, 'Viewer' => 20, 'Seller' => 15, 'Buyer' => 10, 'Employee' => 10];

    public function canMutateBranches(User $actor): bool
    {
        return $actor->hasAnyRole(['Super Admin', 'System Admin']);
    }

    public function assertCanAssign(User $actor, string $targetRole, ?User $target = null): void
    {
        $actorRank = $this->highestRank($actor);
        $targetRank = self::RANKS[$targetRole] ?? 0;
        if ($actorRank < $targetRank || ($targetRole === 'Super Admin' && ! $actor->hasRole('Super Admin'))) {
            throw ValidationException::withMessages(['account_role' => 'Bạn không được cấp vai trò cao hơn thẩm quyền hiện tại.']);
        }
        if ($target?->is($actor) && $targetRank > $actorRank) {
            throw ValidationException::withMessages(['account_role' => 'Không thể tự nâng quyền tài khoản.']);
        }
    }

    /** @return list<string> */
    public function assignableRoles(User $actor): array
    {
        $rank = $this->highestRank($actor);

        return array_keys(array_filter(self::RANKS, fn (int $value, string $role): bool => $value <= $rank && ($role !== 'Super Admin' || $actor->hasRole('Super Admin')), ARRAY_FILTER_USE_BOTH));
    }

    private function highestRank(User $user): int
    {
        $highest = 0;
        foreach ($user->getRoleNames() as $role) {
            if (is_string($role)) {
                $highest = max($highest, self::RANKS[$role] ?? 0);
            }
        }

        return $highest;
    }
}
