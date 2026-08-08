<?php

namespace Modules\Employee\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\EmployeeAssignmentData;
use Modules\Employee\Domain\Enums\AssignmentType;
use Modules\Employee\Domain\Enums\EmployeeHistoryEventType;
use Modules\Employee\Domain\Events\EmployeeAssignmentChanged;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Models\EmployeeHistory;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;

final readonly class EmployeeAssignmentService
{
    public function __construct(private Dispatcher $events) {}

    public function create(int $companyId, int $employeeId, EmployeeAssignmentData $data, int $actorId): EmployeeAssignment
    {
        return DB::transaction(function () use ($companyId, $employeeId, $data, $actorId): EmployeeAssignment {
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $this->validateReferences($companyId, $employee, $data);
            $this->validateAllocation($employeeId, $data);
            $assignment = $employee->assignments()->create($data->toArray());
            $this->history($employeeId, EmployeeHistoryEventType::StatusChange, null, $assignment->toArray(), $actorId, 'Tạo phân công');
            $this->events->dispatch(EmployeeAssignmentChanged::created($companyId, $employeeId, (int) $assignment->id, $actorId));

            return $assignment;
        }, 3);
    }

    public function update(int $companyId, int $assignmentId, EmployeeAssignmentData $data, int $actorId): EmployeeAssignment
    {
        return DB::transaction(function () use ($companyId, $assignmentId, $data, $actorId): EmployeeAssignment {
            $assignment = $this->lockedAssignment($companyId, $assignmentId);
            if (CarbonImmutable::parse((string) $assignment->effective_from)->isBefore(today())) {
                throw ValidationException::withMessages(['assignment' => 'Phân công đã có hiệu lực; hãy dùng chuyển công tác hoặc kết thúc phân công.']);
            }
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($assignment->employee_id);
            $this->validateReferences($companyId, $employee, $data);
            $this->validateAllocation((int) $employee->id, $data, $assignmentId);
            $before = $assignment->toArray();
            $assignment->update($data->toArray());
            $this->history((int) $employee->id, EmployeeHistoryEventType::StatusChange, $before, $assignment->refresh()->toArray(), $actorId, 'Cập nhật phân công');
            $this->events->dispatch(EmployeeAssignmentChanged::updated($companyId, (int) $employee->id, $assignmentId, $actorId));

            return $assignment;
        }, 3);
    }

    public function end(int $companyId, int $assignmentId, CarbonImmutable $effectiveTo, string $reason, int $actorId): EmployeeAssignment
    {
        return DB::transaction(function () use ($companyId, $assignmentId, $effectiveTo, $reason, $actorId): EmployeeAssignment {
            $assignment = $this->lockedAssignment($companyId, $assignmentId);
            if ($effectiveTo->isBefore(CarbonImmutable::parse((string) $assignment->effective_from))) {
                throw ValidationException::withMessages(['effective_to' => 'Ngày kết thúc không được trước ngày bắt đầu.']);
            }
            $before = $assignment->toArray();
            $assignment->update(['effective_to' => $effectiveTo->toDateString()]);
            $this->history((int) $assignment->employee_id, EmployeeHistoryEventType::StatusChange, $before, $assignment->refresh()->toArray(), $actorId, $reason);
            $this->events->dispatch(EmployeeAssignmentChanged::ended($companyId, (int) $assignment->employee_id, $assignmentId, $actorId));

            return $assignment;
        }, 3);
    }

    public function transfer(int $companyId, int $employeeId, int $currentAssignmentId, EmployeeAssignmentData $data, string $reason, int $actorId, bool $promotion): EmployeeAssignment
    {
        return DB::transaction(function () use ($companyId, $employeeId, $currentAssignmentId, $data, $reason, $actorId, $promotion): EmployeeAssignment {
            Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $current = $this->lockedAssignment($companyId, $currentAssignmentId);
            if ((int) $current->employee_id !== $employeeId) {
                throw ValidationException::withMessages(['assignment' => 'Phân công không thuộc nhân viên.']);
            }
            if (! $data->effectiveFrom->isAfter(CarbonImmutable::parse((string) $current->effective_from))) {
                throw ValidationException::withMessages(['effective_from' => 'Ngày chuyển phải sau ngày bắt đầu phân công hiện tại.']);
            }
            $current->update(['effective_to' => $data->effectiveFrom->subDay()->toDateString()]);
            $new = $this->create($companyId, $employeeId, $data, $actorId);
            $this->history(
                $employeeId,
                $promotion ? EmployeeHistoryEventType::Promotion : EmployeeHistoryEventType::Transfer,
                $current->toArray(),
                $new->toArray(),
                $actorId,
                $reason,
            );
            $this->events->dispatch(EmployeeAssignmentChanged::transferred($companyId, $employeeId, (int) $new->id, $actorId, $promotion));

            return $new;
        }, 3);
    }

    private function lockedAssignment(int $companyId, int $assignmentId): EmployeeAssignment
    {
        return EmployeeAssignment::query()
            ->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))
            ->lockForUpdate()
            ->findOrFail($assignmentId);
    }

    private function validateReferences(int $companyId, Employee $employee, EmployeeAssignmentData $data): void
    {
        $unit = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($data->organizationUnitId);
        $position = JobPosition::query()->where('company_id', $companyId)->findOrFail($data->jobPositionId);
        if ((int) $position->organization_unit_id !== (int) $unit->id) {
            throw ValidationException::withMessages(['job_position_id' => 'Vị trí không thuộc đơn vị tổ chức đã chọn.']);
        }
        if ($data->managerId !== null) {
            if ($data->managerId === (int) $employee->id) {
                throw ValidationException::withMessages(['manager_id' => 'Nhân viên không thể tự quản lý chính mình.']);
            }
            Employee::query()->where('company_id', $companyId)->findOrFail($data->managerId);
            $this->validateManagerCycle((int) $employee->id, $data->managerId, $companyId);
        }
        if ($data->effectiveTo !== null && $data->effectiveTo->isBefore($data->effectiveFrom)) {
            throw ValidationException::withMessages(['effective_to' => 'Ngày kết thúc không được trước ngày bắt đầu.']);
        }
    }

    private function validateAllocation(int $employeeId, EmployeeAssignmentData $data, ?int $exceptId = null): void
    {
        $overlapping = EmployeeAssignment::query()
            ->where('employee_id', $employeeId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereDate('effective_from', '<=', $data->effectiveTo?->toDateString() ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data->effectiveFrom->toDateString()))
            ->lockForUpdate()
            ->get();
        $allocated = $overlapping->sum(fn (EmployeeAssignment $assignment): float => (float) $assignment->allocation_percent);
        if ($allocated + (float) $data->allocationPercent > 100.0) {
            throw ValidationException::withMessages(['allocation_percent' => 'Tổng tỷ lệ phân bổ trong thời gian hiệu lực không được vượt 100%.']);
        }
        if ($data->type === AssignmentType::Primary && $overlapping->contains(fn (EmployeeAssignment $assignment): bool => $assignment->getRawOriginal('assignment_type') === AssignmentType::Primary->value)) {
            throw ValidationException::withMessages(['assignment_type' => 'Chỉ được có một phân công chính trong cùng thời gian hiệu lực.']);
        }
    }

    private function validateManagerCycle(int $employeeId, int $managerId, int $companyId): void
    {
        $visited = [$employeeId => true];
        $cursor = $managerId;
        while ($cursor > 0) {
            if (isset($visited[$cursor])) {
                throw ValidationException::withMessages(['manager_id' => 'Quan hệ quản lý tạo vòng lặp.']);
            }
            $visited[$cursor] = true;
            $next = Employee::query()->where('company_id', $companyId)->whereKey($cursor)->value('manager_id');
            $cursor = is_numeric($next) ? (int) $next : 0;
        }
    }

    /**
     * @param  array<mixed, mixed>|null  $before
     * @param  array<mixed, mixed>  $after
     */
    private function history(int $employeeId, EmployeeHistoryEventType $event, ?array $before, array $after, int $actorId, string $reason): void
    {
        EmployeeHistory::query()->create([
            'employee_id' => $employeeId,
            'event_type' => $event,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'approved_by' => $actorId,
            'effective_at' => now(),
        ]);
    }
}
