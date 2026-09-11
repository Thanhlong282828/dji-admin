<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

final readonly class WorkforceNotificationRecipientResolver
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return Collection<int,User> */
    public function approvers(int $companyId, int $employeeId, string $permission): Collection
    {
        $ids = Permission::query()->where(['name' => $permission, 'guard_name' => 'web'])->exists()
            ? User::permission($permission)->whereIn('id', $this->db->table('company_user_access')->where('company_id', $companyId)->pluck('user_id'))->pluck('id')
            : collect();
        $manager = $this->db->table('employees as e')->leftJoin('employees as m', 'm.id', '=', 'e.manager_id')->where('e.company_id', $companyId)->where('e.id', $employeeId)->value('m.user_id');
        if (is_numeric($manager) && $this->db->table('company_user_access')->where('company_id', $companyId)->where('user_id', (int) $manager)->exists()) {
            $ids->push((int) $manager);
        }

        return User::query()->whereIn('id', $ids->unique()->values())->get();
    }

    /** @return Collection<int,User> */
    public function employee(int $companyId, int $employeeId): Collection
    {
        $id = $this->db->table('employees')->where('company_id', $companyId)->where('id', $employeeId)->whereNull('deleted_at')->value('user_id');
        if (! is_numeric($id) || ! $this->db->table('company_user_access')->where('company_id', $companyId)->where('user_id', (int) $id)->exists()) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        return User::query()->where('id', (int) $id)->get();
    }
}
