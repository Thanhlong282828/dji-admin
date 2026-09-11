<?php

namespace Modules\Company\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Services\BranchAuthorityService;
use RuntimeException;

final readonly class BranchManagementService
{
    public function __construct(private ConnectionInterface $db, private BranchAuthorityService $authority) {}

    /** @param array{company_id:int,leader_employee_id:?int,code?:string,name?:string,address?:?string,province?:?string,status?:string,timezone?:string} $data */
    public function create(array $data, User $actor): int
    {
        $this->validateLeader($data['company_id'], $data['leader_employee_id']);
        $branchId = $this->db->transaction(function () use ($data, $actor): int {
            $id = $this->id($this->db->table('branches')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]));
            $this->db->table('branch_user_access')->insert(['branch_id' => $id, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->ensureLeaderAccess($id, $data['leader_employee_id']);

            return $id;
        }, 3);
        activity('commerce.branch')->causedBy($actor)->withProperties(['branch_id' => $branchId, 'company_id' => $data['company_id']])->log('branch.created');

        return $branchId;
    }

    /** @param array{company_id:int,leader_employee_id:?int,code?:string,name?:string,address?:?string,province?:?string,status?:string,timezone?:string} $data */
    public function update(int $branchId, array $data, User $actor): int
    {
        $this->validateLeader($data['company_id'], $data['leader_employee_id']);
        $old = $this->db->table('branches')->where(['id' => $branchId, 'company_id' => $data['company_id']])->value('leader_employee_id');
        $this->db->transaction(function () use ($branchId, $data): void {
            $this->db->table('branches')->where('id', $branchId)->where('company_id', $data['company_id'])->update([...$data, 'updated_at' => now()]);
            $this->ensureLeaderAccess($branchId, $data['leader_employee_id']);
        }, 3);
        activity('commerce.branch')->causedBy($actor)->withProperties(['branch_id' => $branchId, 'company_id' => $data['company_id']])->log('branch.updated');
        $oldLeader = is_numeric($old) ? (int) $old : null;
        if ($oldLeader !== $data['leader_employee_id']) {
            activity('commerce.branch')->causedBy($actor)->withProperties(['branch_id' => $branchId, 'old_leader_employee_id' => $oldLeader, 'new_leader_employee_id' => $data['leader_employee_id']])->log($data['leader_employee_id'] === null ? 'branch_leader_removed' : ($oldLeader === null ? 'branch_leader_assigned' : 'branch_leader_changed'));
        }

        return $branchId;
    }

    public function archive(int $branchId, int $companyId, User $actor): int
    {
        foreach (['employee_branch_assignments', 'attendance_events', 'attendance_records', 'payroll_employee_inputs', 'branch_bonus_proposals', 'branch_management_revenues', 'branch_operating_expenses', 'branch_rent_contracts', 'branch_assets', 'inventory_locations', 'commerce_fulfillments'] as $table) {
            if ($this->db->table($table)->where('branch_id', $branchId)->exists()) {
                throw ValidationException::withMessages(['branch' => 'Chi nhánh đã có dữ liệu vận hành nên không thể xóa. Hãy chuyển trạng thái sang Tạm ngưng/Lưu trữ để giữ lịch sử.']);
            }
        }
        $this->db->table('branches')->where('id', $branchId)->where('company_id', $companyId)->whereNull('deleted_at')->update(['status' => 'archived', 'deleted_at' => now(), 'updated_at' => now()]);
        activity('commerce.branch')->causedBy($actor)->withProperties(['branch_id' => $branchId, 'company_id' => $companyId])->log('branch.archived');

        return $branchId;
    }

    private function validateLeader(int $companyId, ?int $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }
        $valid = $this->db->table('employees as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('employee_assignments as a', static function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->join('model_has_roles as mhr', static function (JoinClause $join): void {
            $join->on('mhr.model_id', '=', 'u.id')->where('mhr.model_type', User::class);
        })->join('roles as r', 'r.id', '=', 'mhr.role_id')->where('e.id', $employeeId)->where('e.company_id', $companyId)->whereIn('e.employment_status', ['active', 'probation'])->where('u.access_enabled', true)->whereIn('r.name', $this->authority->leaderRoles())->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['leader_employee_id' => 'Lãnh đạo phải là Employee cùng công ty, có account hoạt động, vai trò quản lý được phép và vị trí hiện tại.']);
        }
    }

    private function ensureLeaderAccess(int $branchId, ?int $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }
        $userId = $this->db->table('employees')->where('id', $employeeId)->value('user_id');
        if (is_numeric($userId)) {
            $this->db->table('branch_user_access')->updateOrInsert(['branch_id' => $branchId, 'user_id' => (int) $userId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function id(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new RuntimeException('Database did not return a valid Branch ID.');
    }
}
