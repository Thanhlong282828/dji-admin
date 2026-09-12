<?php

namespace Modules\Workflow\Infrastructure\Resolvers;

use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Domain\Contracts\WorkflowActorResolverInterface;

final class DatabaseWorkflowActorResolver implements WorkflowActorResolverInterface
{
    /**
     * @param  array<string, scalar|null>  $context
     * @return list<int>
     */
    public function resolve(int $companyId, string $type, string $value, string $subjectType, int $subjectId, array $context): array
    {
        $ids = match ($type) {
            'specific_user' => is_numeric($value) ? [(int) $value] : [],
            'manager' => $subjectType === 'employee' ? DB::table('employees as e')->join('employees as manager', 'manager.id', '=', 'e.manager_id')->where('e.company_id', $companyId)->where('e.id', $subjectId)->whereNotNull('manager.user_id')->pluck('manager.user_id')->all() : [],
            'permission' => $this->permissionActors($companyId, $value),
            'organization_role' => DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->join('company_user_access as ca', 'ca.user_id', '=', 'mr.model_id')->where('mr.model_type', User::class)->where('ca.company_id', $companyId)->where('r.name', $value)->pluck('mr.model_id')->all(),
            default => [],
        };

        $normalized = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $normalized[] = (int) $id;
            }
        }

        $eligible = DB::table('company_user_access')
            ->where('company_id', $companyId)
            ->whereIn('user_id', array_values(array_unique($normalized)))
            ->pluck('user_id')
            ->all();
        $result = [];
        foreach ($eligible as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $result[] = (int) $id;
            }
        }

        return array_values(array_unique($result));
    }

    /** @return list<int> */
    private function permissionActors(int $companyId, string $permission): array
    {
        $configuredGuard = config('auth.defaults.guard');
        if (! is_string($configuredGuard) || $configuredGuard === '') {
            return [];
        }
        $guard = $configuredGuard;
        $direct = DB::table('model_has_permissions as mp')
            ->join('permissions as p', 'p.id', '=', 'mp.permission_id')
            ->where('mp.model_type', User::class)
            ->where('p.name', $permission)
            ->where('p.guard_name', $guard)
            ->selectRaw('mp.model_id user_id, p.guard_name guard_name');

        $inherited = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', function (JoinClause $join): void {
                $join->on('p.id', '=', 'rp.permission_id')->on('p.guard_name', '=', 'r.guard_name');
            })
            ->where('mr.model_type', User::class)
            ->where('p.name', $permission)
            ->where('p.guard_name', $guard)
            ->selectRaw('mr.model_id user_id, p.guard_name guard_name');

        $ids = DB::table('company_user_access as ca')
            ->join('users as u', 'u.id', '=', 'ca.user_id')
            ->joinSub($direct->union($inherited), 'effective_permission', 'effective_permission.user_id', '=', 'ca.user_id')
            ->where('ca.company_id', $companyId)
            ->where('u.access_enabled', true)
            ->distinct()
            ->orderBy('ca.user_id')
            ->pluck('ca.user_id');
        $actors = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $actors[] = (int) $id;
            }
        }

        return $actors;
    }
}
