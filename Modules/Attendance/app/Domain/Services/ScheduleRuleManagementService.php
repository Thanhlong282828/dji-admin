<?php

namespace Modules\Attendance\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Application\Commands\ManageScheduleRulesCommand;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;

final readonly class ScheduleRuleManagementService
{
    public function __construct(private ConnectionInterface $db) {}

    public function execute(ManageScheduleRulesCommand $command): int
    {
        return $this->db->transaction(fn (): int => $command->action === 'calendar.days.update' ? $this->calendarDays($command) : $this->breakRule($command), 3);
    }

    private function calendarDays(ManageScheduleRulesCommand $command): int
    {
        $calendar = $this->db->table('work_calendars')->where('company_id', $command->companyId)->where('id', $command->parentId)->lockForUpdate()->first();
        abort_if($calendar === null, 404);
        $this->version($calendar, $command->expectedVersion);
        if (($calendar->status ?? null) !== 'active') {
            throw ValidationException::withMessages(['calendar' => 'Chỉ sửa ngày của calendar đang hoạt động.']);
        }
        $days = $command->data['days'] ?? null;
        if (! is_array($days) || count($days) !== 7) {
            throw ValidationException::withMessages(['days' => 'Calendar phải có đúng 7 ngày ISO.']);
        }
        $weekdays = [];
        foreach ($days as $day) {
            $weekday = $day['weekday'];
            if ($weekday < 1 || $weekday > 7 || in_array($weekday, $weekdays, true)) {
                throw ValidationException::withMessages(['days' => 'Weekday phải duy nhất từ 1 đến 7.']);
            }
            $weekdays[] = $weekday;
            if ($day['is_working_day'] && $day['planned_minutes'] <= 0 || ! $day['is_working_day'] && $day['planned_minutes'] !== 0) {
                throw ValidationException::withMessages(['days' => 'Planned minutes không phù hợp trạng thái working day.']);
            }
            if ($day['default_shift_id'] !== null) {
                $valid = $this->db->table('work_shifts')->where('company_id', $command->companyId)->where('id', $day['default_shift_id'])->where('status', 'active')->exists();
                if (! $valid) {
                    throw ValidationException::withMessages(['days' => 'Default shift không hợp lệ hoặc khác company.']);
                }
            }
            $this->db->table('work_calendar_days')->updateOrInsert(['work_calendar_id' => $command->parentId, 'weekday' => $weekday], ['is_working_day' => $day['is_working_day'], 'planned_minutes' => $day['planned_minutes'], 'default_shift_id' => $day['default_shift_id'], 'created_at' => now(), 'updated_at' => now()]);
        }
        $version = $command->expectedVersion + 1;
        $this->db->table('work_calendars')->where('id', $command->parentId)->update(['weekly_work_minutes' => array_sum(array_column($days, 'planned_minutes')), 'version' => $version, 'updated_at' => now()]);
        $this->audit($command, $command->parentId);
        event(new WorkforceAttendanceChanged($command->companyId, $command->parentId, 0, 'calendar.days.updated'));

        return $command->parentId;
    }

    private function breakRule(ManageScheduleRulesCommand $command): int
    {
        $shift = $this->db->table('work_shifts')->where('company_id', $command->companyId)->where('id', $command->parentId)->lockForUpdate()->first();
        abort_if($shift === null, 404);
        if ($command->action === 'break.archive') {
            $rule = $this->rule($command);
            $this->version($rule, $command->expectedVersion);
            $id = $this->int($rule, 'id');
            $this->db->table('shift_break_rules')->where('id', $id)->update(['status' => 'archived', 'effective_to' => today(), 'version' => $command->expectedVersion + 1, 'updated_at' => now()]);
        } else {
            $mode = $this->string($command, 'mode');
            $start = $this->nullableString($command, 'starts_at');
            $end = $this->nullableString($command, 'ends_at');
            $duration = $this->integer($command, 'duration_minutes');
            if ($duration <= 0 || $mode === 'fixed' && ($start === null || $end === null)) {
                throw ValidationException::withMessages(['duration_minutes' => 'Fixed break cần start/end và duration phải lớn hơn 0.']);
            }
            if ($mode === 'fixed') {
                $this->validateFixedWindow($shift, $start, $end, $duration, $command);
            }
            $total = (int) $this->db->table('shift_break_rules')->where('work_shift_id', $command->parentId)->where('status', 'active')->when($command->ruleId !== null, fn ($q) => $q->where('id', '<>', $command->ruleId))->sum('duration_minutes');
            if ($total + $duration > $this->int($shift, 'planned_work_minutes')) {
                throw ValidationException::withMessages(['duration_minutes' => 'Tổng break vượt thời lượng ca.']);
            }
            $values = ['code' => strtoupper($this->string($command, 'code')), 'name' => $this->nullableString($command, 'name'), 'kind' => $this->string($command, 'kind'), 'mode' => $mode, 'is_paid' => $this->boolean($command, 'is_paid'), 'starts_at' => $start, 'ends_at' => $end, 'duration_minutes' => $duration, 'minimum_work_minutes' => $this->integer($command, 'minimum_work_minutes'), 'sort_order' => $this->integer($command, 'sort_order'), 'status' => 'active', 'effective_from' => $this->nullableString($command, 'effective_from') ?? today()->toDateString(), 'effective_to' => null, 'updated_at' => now()];
            if ($command->action === 'break.create') {
                $id = (int) $this->db->table('shift_break_rules')->insertGetId($values + ['work_shift_id' => $command->parentId, 'version' => 1, 'created_at' => now()]);
            } else {
                $rule = $this->rule($command);
                $this->version($rule, $command->expectedVersion);
                $id = $this->int($rule, 'id');
                $this->db->table('shift_break_rules')->where('id', $id)->update($values + ['version' => $command->expectedVersion + 1]);
            }
        }
        $this->audit($command, $id);
        event(new WorkforceAttendanceChanged($command->companyId, $id, 0, $command->action));

        return $id;
    }

    private function validateFixedWindow(object $shift, ?string $start, ?string $end, int $duration, ManageScheduleRulesCommand $command): void
    {
        $shiftStart = $this->clock($this->text($shift, 'starts_at'));
        $shiftEnd = $this->clock($this->text($shift, 'ends_at'));
        if ((bool) ($shift->crosses_midnight ?? false) && $shiftEnd <= $shiftStart) {
            $shiftEnd += 1440;
        }
        $breakStart = $this->clock((string) $start);
        $breakEnd = $this->clock((string) $end);
        if ($breakEnd <= $breakStart) {
            $breakEnd += 1440;
        }
        if ($breakStart < $shiftStart) {
            $breakStart += 1440;
            $breakEnd += 1440;
        }
        if ($breakStart < $shiftStart || $breakEnd > $shiftEnd || $breakEnd - $breakStart !== $duration) {
            throw ValidationException::withMessages(['starts_at' => 'Fixed break phải nằm trong shift window và khớp duration.']);
        }
        $overlap = $this->db->table('shift_break_rules')->where('work_shift_id', $command->parentId)->where('mode', 'fixed')->where('status', 'active')->when($command->ruleId !== null, fn ($q) => $q->where('id', '<>', $command->ruleId))->get(['starts_at', 'ends_at'])->contains(function (object $row) use ($breakStart, $breakEnd, $shiftStart): bool {
            $s = $this->clock($this->text($row, 'starts_at'));
            $e = $this->clock($this->text($row, 'ends_at'));
            if ($e <= $s) {
                $e += 1440;
            } if ($s < $shiftStart) {
                $s += 1440;
                $e += 1440;
            }

            return $s < $breakEnd && $e > $breakStart;
        });
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Fixed break bị overlap.']);
        }
    }

    private function rule(ManageScheduleRulesCommand $command): object
    {
        $row = $this->db->table('shift_break_rules')->join('work_shifts', 'work_shifts.id', '=', 'shift_break_rules.work_shift_id')->where('work_shifts.company_id', $command->companyId)->where('shift_break_rules.work_shift_id', $command->parentId)->where('shift_break_rules.id', $command->ruleId)->select('shift_break_rules.*')->lockForUpdate()->first();
        abort_if($row === null, 404);

        return $row;
    }

    private function version(object $row, int $version): void
    {
        if ($this->int($row, 'version') !== $version) {
            throw ValidationException::withMessages(['version' => 'Dữ liệu đã thay đổi.']);
        }
    }

    private function audit(ManageScheduleRulesCommand $command, int $id): void
    {
        activity('workforce')->causedBy($command->actorId)->event($command->action)->withProperties(['company_id' => $command->companyId, 'subject_id' => $id])->log('Workforce '.$command->action);
    }

    private function clock(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $hour * 60 + $minute;
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }

    private function string(ManageScheduleRulesCommand $command, string $key): string
    {
        $value = $command->data[$key] ?? null;
        if (! is_string($value)) {
            throw ValidationException::withMessages([$key => 'Giá trị không hợp lệ.']);
        }

        return $value;
    }

    private function nullableString(ManageScheduleRulesCommand $command, string $key): ?string
    {
        $value = $command->data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(ManageScheduleRulesCommand $command, string $key): int
    {
        $value = $command->data[$key] ?? null;
        if (! is_int($value)) {
            throw ValidationException::withMessages([$key => 'Số nguyên không hợp lệ.']);
        }

        return $value;
    }

    private function boolean(ManageScheduleRulesCommand $command, string $key): bool
    {
        return ($command->data[$key] ?? false) === true;
    }
}
