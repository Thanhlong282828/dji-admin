<?php

namespace Modules\Attendance\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Application\Commands\ManageWorkforceResourceCommand;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;

final readonly class WorkforceManagementService
{
    public function __construct(private ConnectionInterface $database) {}

    public function execute(ManageWorkforceResourceCommand $command): int
    {
        return $this->database->transaction(function () use ($command): int {
            $table = match ($command->resource) {
                'calendar' => 'work_calendars', 'holiday' => 'company_holidays', 'shift' => 'work_shifts', 'assignment' => 'employee_shift_assignments',
                default => throw ValidationException::withMessages(['resource' => 'Workforce resource không hợp lệ.']),
            };
            if ($command->action === 'delete') {
                $id = $this->scopedId($table, $command->companyId, $command->resourceId);
                if ($command->resource === 'calendar' && ($this->database->table('employee_work_profiles')->where('work_calendar_id', $id)->exists() || $this->database->table('company_holidays')->where('work_calendar_id', $id)->exists())) {
                    throw ValidationException::withMessages(['calendar' => 'Không thể xóa lịch đang được sử dụng.']);
                }
                if ($command->resource === 'shift' && $this->database->table('employee_shift_assignments')->where('work_shift_id', $id)->exists()) {
                    throw ValidationException::withMessages(['shift' => 'Không thể xóa ca đang được phân công.']);
                }
                $this->database->table($table)->where('id', $id)->delete();
            } else {
                $values = $this->normalize($command);
                $values['updated_at'] = now();
                if ($command->resourceId === null) {
                    $values['company_id'] = $command->companyId;
                    $values['created_at'] = now();
                    $id = (int) $this->database->table($table)->insertGetId($values);
                } else {
                    $id = $this->scopedId($table, $command->companyId, $command->resourceId);
                    $this->database->table($table)->where('id', $id)->update($values);
                }
            }
            activity('workforce')->causedBy($command->actorId)->event($command->action)->withProperties(['company_id' => $command->companyId, 'resource' => $command->resource, 'resource_id' => $id])->log(ucfirst($command->resource).' '.$command->action);
            event(new WorkforceAttendanceChanged($command->companyId, $id, 0, $command->resource.'.'.$command->action));

            return $id;
        }, 3);
    }

    private function scopedId(string $table, int $companyId, ?int $id): int
    {
        if ($id === null) {
            throw ValidationException::withMessages(['id' => 'Thiếu ID.']);
        }
        $found = $this->database->table($table)->where('company_id', $companyId)->where('id', $id)->value('id');
        if (! is_numeric($found)) {
            abort(404);
        }

        return (int) $found;
    }

    /** @return array<string, bool|int|string|null> */
    private function normalize(ManageWorkforceResourceCommand $command): array
    {
        $a = $command->attributes;

        if ($command->resource === 'holiday' && is_int($a['work_calendar_id'] ?? null)) {
            $this->scopedId('work_calendars', $command->companyId, $a['work_calendar_id']);
        }
        if ($command->resource === 'assignment') {
            $employeeId = $this->scopedId('employees', $command->companyId, (int) $a['employee_id']);
            $this->database->table('employees')->where('id', $employeeId)->lockForUpdate()->value('id');
            $this->scopedId('work_shifts', $command->companyId, (int) $a['work_shift_id']);
            $from = (string) $a['effective_from'];
            $to = is_string($a['effective_to'] ?? null) && $a['effective_to'] !== '' ? $a['effective_to'] : '9999-12-31';
            $overlap = $this->database->table('employee_shift_assignments')
                ->where('company_id', $command->companyId)
                ->where('employee_id', $employeeId)
                ->whereDate('effective_from', '<=', $to)
                ->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->when($command->resourceId !== null, static fn (Builder $query): Builder => $query->where('id', '<>', $command->resourceId))
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['assignment' => 'Nhân viên đã có ca làm việc trùng khoảng hiệu lực.']);
            }
        }
        if (in_array($command->resource, ['calendar', 'shift'], true)) {
            $table = $command->resource === 'calendar' ? 'work_calendars' : 'work_shifts';
            $duplicate = $this->database->table($table)->where('company_id', $command->companyId)->where('code', strtoupper((string) $a['code']))->when($command->resourceId !== null, fn ($query) => $query->where('id', '<>', $command->resourceId))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['code' => 'Mã đã tồn tại trong công ty.']);
            }
        }
        if ($command->resource === 'holiday') {
            $duplicate = $this->database->table('company_holidays')->where('company_id', $command->companyId)->whereDate('holiday_date', (string) $a['holiday_date'])->when($command->resourceId !== null, fn ($query) => $query->where('id', '<>', $command->resourceId))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['holiday_date' => 'Ngày này đã có cấu hình holiday.']);
            }
        }

        return match ($command->resource) {
            'calendar' => ['code' => strtoupper((string) $a['code']), 'name' => (string) $a['name'], 'timezone' => (string) $a['timezone'], 'weekly_work_minutes' => (int) $a['weekly_work_minutes'], 'status' => (string) $a['status'], 'effective_from' => (string) $a['effective_from'], 'effective_to' => $a['effective_to'], 'version' => 1],
            'holiday' => ['work_calendar_id' => $a['work_calendar_id'], 'holiday_date' => (string) $a['holiday_date'], 'name' => (string) $a['name'], 'is_paid' => (bool) $a['is_paid'], 'recurring' => (bool) $a['recurring'], 'is_working_override' => (bool) $a['is_working_override'], 'country_code' => $a['country_code'], 'region' => $a['region'], 'source' => 'manual'],
            'shift' => ['code' => strtoupper((string) $a['code']), 'name' => (string) $a['name'], 'timezone' => (string) $a['timezone'], 'starts_at' => (string) $a['starts_at'], 'ends_at' => (string) $a['ends_at'], 'crosses_midnight' => (bool) $a['crosses_midnight'], 'planned_work_minutes' => (int) $a['planned_work_minutes'], 'grace_in_minutes' => (int) $a['grace_in_minutes'], 'grace_out_minutes' => (int) $a['grace_out_minutes'], 'rounding_rule' => (string) $a['rounding_rule'], 'minimum_half_day_minutes' => (int) $a['minimum_half_day_minutes'], 'minimum_full_day_minutes' => (int) $a['minimum_full_day_minutes'], 'status' => (string) $a['status'], 'rules' => '{}', 'version' => 1],
            'assignment' => ['employee_id' => (int) $a['employee_id'], 'work_shift_id' => (int) $a['work_shift_id'], 'assignment_type' => (string) $a['assignment_type'], 'source' => 'manual', 'is_primary' => true, 'effective_from' => (string) $a['effective_from'], 'effective_to' => $a['effective_to'], 'days_of_week' => null, 'rotation_pattern' => null, 'created_by' => $command->actorId, 'version' => 1],
            default => [],
        };
    }
}
