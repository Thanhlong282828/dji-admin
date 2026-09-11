<?php

namespace Modules\Project\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

final readonly class ProjectManagementService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $data */
    public function createProject(array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($data, $actorId): int {
            $companyId = $this->positive($data['company_id'] ?? null);
            $managerId = $this->positive($data['manager_employee_id'] ?? null);
            $this->employeeInCompany($companyId, $managerId);
            $this->branchInCompany($companyId, $data['branch_id'] ?? null);
            $id = (int) $this->db->table('projects')->insertGetId([
                'company_id' => $companyId, 'branch_id' => ($data['branch_id'] ?? null) ?: null,
                'manager_employee_id' => $managerId, 'code' => $data['code'], 'name' => $data['name'],
                'description' => $data['description'] ?? null, 'project_type' => $data['project_type'],
                'status' => 'DRAFT', 'priority' => $data['priority'], 'start_date' => $data['start_date'],
                'target_end_date' => $data['target_end_date'] ?? null, 'actual_end_date' => null,
                'budget_minor' => $data['budget_minor'] ?? null, 'currency' => 'VND',
                'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('project_members')->insert(['project_id' => $id, 'employee_id' => $managerId, 'role' => 'PROJECT_MANAGER', 'allocation_percent' => null, 'joined_at' => $data['start_date'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit('created', $companyId, $id, $actorId);

            return $id;
        });
    }

    /** @param array<string,mixed> $data */
    public function updateProject(int $companyId, int $projectId, array $data, int $actorId): void
    {
        $project = $this->project($companyId, $projectId, true);
        abort_if(in_array($this->rowString($project, 'status'), ['COMPLETED', 'CANCELLED'], true), 409, 'Dự án đã đóng không thể sửa trực tiếp.');
        $managerId = $this->positive($data['manager_employee_id'] ?? null);
        $this->employeeInCompany($companyId, $managerId);
        $this->branchInCompany($companyId, $data['branch_id'] ?? null);
        $this->db->transaction(function () use ($projectId, $data, $managerId, $actorId, $companyId): void {
            $this->db->table('projects')->where('id', $projectId)->update(['branch_id' => ($data['branch_id'] ?? null) ?: null, 'manager_employee_id' => $managerId, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'project_type' => $data['project_type'], 'priority' => $data['priority'], 'start_date' => $data['start_date'], 'target_end_date' => $data['target_end_date'] ?? null, 'budget_minor' => $data['budget_minor'] ?? null, 'updated_by' => $actorId, 'updated_at' => now()]);
            $this->db->table('project_members')->updateOrInsert(['project_id' => $projectId, 'employee_id' => $managerId], ['role' => 'PROJECT_MANAGER', 'joined_at' => $data['start_date'], 'left_at' => null, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
            $this->audit('updated', $companyId, $projectId, $actorId);
        });
    }

    public function changeProjectStatus(int $companyId, int $projectId, string $status, int $actorId): void
    {
        $this->db->transaction(function () use ($companyId, $projectId, $status, $actorId): void {
            $project = $this->project($companyId, $projectId, true);
            $currentStatus = $this->rowString($project, 'status');
            if ($currentStatus === $status && $status === 'COMPLETED') {
                return;
            }
            $allowed = ['DRAFT' => ['PLANNED', 'CANCELLED'], 'PLANNED' => ['ACTIVE', 'ON_HOLD', 'CANCELLED'], 'ACTIVE' => ['ON_HOLD', 'COMPLETED', 'CANCELLED'], 'ON_HOLD' => ['ACTIVE', 'CANCELLED'], 'COMPLETED' => [], 'CANCELLED' => []];
            abort_unless(in_array($status, $allowed[$currentStatus] ?? [], true), 409, 'Chuyển trạng thái dự án không hợp lệ.');
            if ($status === 'COMPLETED') {
                abort_if($this->db->table('project_tasks')->where('project_id', $projectId)->whereNotIn('status', ['DONE', 'CANCELLED'])->exists(), 409, 'Dự án còn công việc chưa đóng.');
                abort_if($this->db->table('project_milestones')->where('project_id', $projectId)->where('status', '!=', 'COMPLETED')->exists(), 409, 'Dự án còn milestone chưa hoàn thành.');
                abort_if($this->db->table('project_timesheets')->where('project_id', $projectId)->where('status', 'SUBMITTED')->exists(), 409, 'Dự án còn timesheet chờ duyệt.');
            }
            $this->db->table('projects')->where('id', $projectId)->update(['status' => $status, 'actual_end_date' => $status === 'COMPLETED' ? today() : null, 'updated_by' => $actorId, 'updated_at' => now()]);
            $this->audit('status_changed', $companyId, $projectId, $actorId, ['status' => $status]);
        });
    }

    /** @param array<string,mixed> $data */
    public function addMember(int $companyId, int $projectId, array $data, int $actorId): void
    {
        $this->project($companyId, $projectId);
        $employeeId = $this->positive($data['employee_id'] ?? null);
        $this->employeeInCompany($companyId, $employeeId);
        try {
            $this->db->table('project_members')->insert(['project_id' => $projectId, 'employee_id' => $employeeId, 'role' => $data['role'], 'allocation_percent' => $data['allocation_percent'] ?? null, 'joined_at' => $data['joined_at'], 'left_at' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException) {
            abort(409, 'Nhân viên đã thuộc dự án.');
        }
        $this->audit('member_added', $companyId, $projectId, $actorId, ['employee_id' => $employeeId]);
    }

    /** @param array<string,mixed> $data */
    public function createMilestone(int $companyId, int $projectId, array $data, int $actorId): int
    {
        $this->project($companyId, $projectId);
        $id = (int) $this->db->table('project_milestones')->insertGetId(['project_id' => $projectId, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'planned_date' => $data['planned_date'], 'status' => 'PENDING', 'sequence' => $data['sequence'] ?? 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('milestone_created', $companyId, $projectId, $actorId, ['milestone_id' => $id]);

        return $id;
    }

    public function changeMilestoneStatus(int $companyId, int $projectId, int $milestoneId, string $status, int $actorId): void
    {
        $this->project($companyId, $projectId);
        $row = $this->db->table('project_milestones')->where(['id' => $milestoneId, 'project_id' => $projectId])->first();
        abort_unless($row !== null, 404);
        $allowed = ['PENDING' => ['IN_PROGRESS', 'BLOCKED'], 'IN_PROGRESS' => ['BLOCKED', 'COMPLETED'], 'BLOCKED' => ['IN_PROGRESS'], 'COMPLETED' => []];
        $currentStatus = $this->rowString($row, 'status');
        if ($currentStatus === $status && $status === 'COMPLETED') {
            return;
        }
        abort_unless(in_array($status, $allowed[$currentStatus] ?? [], true), 409, 'Chuyển trạng thái milestone không hợp lệ.');
        $this->db->table('project_milestones')->where('id', $milestoneId)->update(['status' => $status, 'completed_at' => $status === 'COMPLETED' ? now() : null, 'updated_at' => now()]);
        $this->audit('milestone_status_changed', $companyId, $projectId, $actorId, ['milestone_id' => $milestoneId, 'status' => $status]);
    }

    /** @param array<string,mixed> $data */
    public function createTask(int $companyId, int $projectId, array $data, int $actorId): int
    {
        $this->project($companyId, $projectId);
        $this->relatedToProject('project_milestones', $data['milestone_id'] ?? null, $projectId);
        $this->relatedToProject('project_tasks', $data['parent_task_id'] ?? null, $projectId);
        if (! empty($data['assignee_employee_id'])) {
            $this->activeMember($projectId, $this->positive($data['assignee_employee_id']));
        }
        $id = (int) $this->db->table('project_tasks')->insertGetId(['project_id' => $projectId, 'milestone_id' => $data['milestone_id'] ?? null, 'parent_task_id' => $data['parent_task_id'] ?? null, 'assignee_employee_id' => $data['assignee_employee_id'] ?? null, 'code' => $data['code'], 'title' => $data['title'], 'description' => $data['description'] ?? null, 'priority' => $data['priority'], 'status' => 'TODO', 'start_date' => $data['start_date'] ?? null, 'due_date' => $data['due_date'] ?? null, 'estimated_minutes' => $data['estimated_minutes'] ?? null, 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('task_created', $companyId, $projectId, $actorId, ['task_id' => $id]);

        return $id;
    }

    public function changeTaskStatus(int $companyId, int $projectId, int $taskId, string $status, int $actorId): void
    {
        $this->db->transaction(function () use ($companyId, $projectId, $taskId, $status, $actorId): void {
            $this->project($companyId, $projectId);
            $task = $this->db->table('project_tasks')->where(['id' => $taskId, 'project_id' => $projectId])->lockForUpdate()->first();
            abort_unless($task !== null, 404);
            $currentStatus = $this->rowString($task, 'status');
            if ($currentStatus === $status) {
                return;
            }
            $allowed = ['TODO' => ['IN_PROGRESS', 'BLOCKED', 'CANCELLED'], 'IN_PROGRESS' => ['BLOCKED', 'DONE', 'CANCELLED'], 'BLOCKED' => ['IN_PROGRESS', 'CANCELLED'], 'DONE' => [], 'CANCELLED' => []];
            abort_unless(in_array($status, $allowed[$currentStatus] ?? [], true), 409, 'Chuyển trạng thái công việc không hợp lệ.');
            if ($status === 'DONE') {
                $blocked = $this->db->table('project_task_dependencies as d')->join('project_tasks as p', 'p.id', '=', 'd.predecessor_task_id')->where('d.successor_task_id', $taskId)->where('p.status', '!=', 'DONE')->exists();
                abort_if($blocked, 409, 'Công việc tiền nhiệm chưa hoàn thành.');
            }
            $this->db->table('project_tasks')->where('id', $taskId)->update(['status' => $status, 'completed_at' => $status === 'DONE' ? now() : null, 'updated_at' => now()]);
            $this->audit('task_status_changed', $companyId, $projectId, $actorId, ['task_id' => $taskId, 'status' => $status]);
        });
    }

    public function addDependency(int $companyId, int $projectId, int $predecessorId, int $successorId, int $actorId): void
    {
        $this->project($companyId, $projectId);
        abort_if($predecessorId === $successorId, 422, 'Công việc không thể phụ thuộc chính nó.');
        $this->relatedToProject('project_tasks', $predecessorId, $projectId);
        $this->relatedToProject('project_tasks', $successorId, $projectId);
        abort_if($this->wouldCycle($predecessorId, $successorId), 422, 'Phụ thuộc tạo thành chu trình.');
        $this->db->table('project_task_dependencies')->insertOrIgnore(['predecessor_task_id' => $predecessorId, 'successor_task_id' => $successorId, 'type' => 'FINISH_TO_START', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('task_dependency_added', $companyId, $projectId, $actorId, ['predecessor' => $predecessorId, 'successor' => $successorId]);
    }

    /** @param array<string,mixed> $data */
    public function createTimesheet(int $companyId, int $projectId, array $data, int $actorId): int
    {
        $this->project($companyId, $projectId);
        $employeeId = $this->positive($data['employee_id'] ?? null);
        $this->activeMember($projectId, $employeeId);
        $this->relatedToProject('project_tasks', $data['task_id'] ?? null, $projectId);

        return (int) $this->db->table('project_timesheets')->insertGetId(['project_id' => $projectId, 'task_id' => $data['task_id'] ?? null, 'employee_id' => $employeeId, 'work_date' => $data['work_date'], 'minutes' => $data['minutes'], 'description' => $data['description'], 'status' => 'DRAFT', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function submitTimesheet(int $companyId, int $projectId, int $timesheetId, int $actorId): void
    {
        $this->project($companyId, $projectId);
        $existing = $this->db->table('project_timesheets')->where(['id' => $timesheetId, 'project_id' => $projectId, 'created_by' => $actorId])->first();
        abort_unless($existing !== null, 404);
        if ($this->rowString($existing, 'status') === 'SUBMITTED') {
            return;
        }
        $updated = $this->db->table('project_timesheets')->where(['id' => $timesheetId, 'project_id' => $projectId, 'status' => 'DRAFT', 'created_by' => $actorId])->update(['status' => 'SUBMITTED', 'submitted_at' => now(), 'updated_at' => now()]);
        abort_if($updated !== 1, 409, 'Timesheet không thể gửi.');
        $this->audit('timesheet_submitted', $companyId, $projectId, $actorId, ['timesheet_id' => $timesheetId]);
    }

    public function approveTimesheet(int $companyId, int $projectId, int $timesheetId, int $actorId, bool $approve): void
    {
        $this->db->transaction(function () use ($companyId, $projectId, $timesheetId, $actorId, $approve): void {
            $this->project($companyId, $projectId);
            $row = $this->db->table('project_timesheets')->where(['id' => $timesheetId, 'project_id' => $projectId])->lockForUpdate()->first();
            abort_unless($row !== null, 404);
            $status = $approve ? 'APPROVED' : 'REJECTED';
            $currentStatus = $this->rowString($row, 'status');
            if ($currentStatus === $status) {
                return;
            }
            abort_unless($currentStatus === 'SUBMITTED', 409, 'Timesheet không ở trạng thái chờ duyệt.');
            abort_if($this->rowInt($row, 'created_by') === $actorId, 403, 'Người tạo không được tự duyệt timesheet.');
            $this->db->table('project_timesheets')->where('id', $timesheetId)->update(['status' => $status, 'approved_at' => now(), 'approved_by' => $actorId, 'updated_at' => now()]);
            $this->audit('timesheet_'.strtolower($status), $companyId, $projectId, $actorId, ['timesheet_id' => $timesheetId]);
        });
    }

    private function project(int $companyId, int $projectId, bool $lock = false): object
    {
        $query = $this->db->table('projects')->where(['id' => $projectId, 'company_id' => $companyId]);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function employeeInCompany(int $companyId, int $employeeId): void
    {
        abort_unless($this->db->table('employees')->where(['id' => $employeeId, 'company_id' => $companyId])->exists(), 404);
    }

    private function branchInCompany(int $companyId, mixed $branchId): void
    {
        if ($branchId !== null && $branchId !== '') {
            abort_unless($this->db->table('branches')->where(['id' => $this->positive($branchId), 'company_id' => $companyId])->exists(), 404);
        }
    }

    private function activeMember(int $projectId, int $employeeId): void
    {
        abort_unless($this->db->table('project_members')->where(['project_id' => $projectId, 'employee_id' => $employeeId, 'is_active' => true])->exists(), 422, 'Nhân viên chưa phải thành viên hoạt động của dự án.');
    }

    private function relatedToProject(string $table, mixed $id, int $projectId): void
    {
        if ($id !== null && $id !== '') {
            abort_unless($this->db->table($table)->where(['id' => $this->positive($id), 'project_id' => $projectId])->exists(), 422);
        }
    }

    private function wouldCycle(int $predecessor, int $successor): bool
    {
        $seen = [];
        $queue = [$successor];
        while ($queue !== []) {
            $node = array_shift($queue);
            if ($node === $predecessor) {
                return true;
            }
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            $queue = [...$queue, ...$this->db->table('project_task_dependencies')->where('predecessor_task_id', $node)->pluck('successor_task_id')->map(fn (mixed $id): int => $this->positive($id))->all()];
        }

        return false;
    }

    private function positive(mixed $value): int
    {
        $id = is_numeric($value) ? (int) $value : 0;
        abort_if($id < 1, 422);

        return $id;
    }

    private function rowString(object $row, string $property): string
    {
        $values = get_object_vars($row);
        $value = $values[$property] ?? null;
        abort_unless(is_string($value), 500, 'Project persistence value is invalid.');

        return $value;
    }

    private function rowInt(object $row, string $property): int
    {
        $values = get_object_vars($row);
        $value = $values[$property] ?? null;
        abort_unless(is_numeric($value), 500, 'Project persistence identifier is invalid.');

        return (int) $value;
    }

    /** @param array<string,mixed> $extra */
    private function audit(string $event, int $companyId, int $projectId, int $actorId, array $extra = []): void
    {
        activity('project')->causedBy($actorId)->event($event)->withProperties(['company_id' => $companyId, 'project_id' => $projectId, ...$extra])->log('Project operation');
    }
}
