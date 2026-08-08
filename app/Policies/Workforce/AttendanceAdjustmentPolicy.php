<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AttendanceAdjustmentPolicy extends AbstractWorkforcePolicy
{
    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'attendance.view', $c, 'attendance_adjustment_requests', $id);
    }

    public function create(User $u, int $c, int $record): bool
    {
        return $this->record($u, 'attendance.adjust', $c, 'attendance_records', $record) && $this->unlockedAttendanceRecord($record);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'attendance.adjust', $c, $id, ['draft']);
    }

    public function submit(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'attendance.adjust', $c, $id, ['draft']);
    }

    public function approve(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'attendance.approve', $c, $id, ['submitted']);
    }

    public function reject(User $u, int $c, int $id): bool
    {
        return $this->approve($u, $c, $id);
    }

    public function cancel(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'attendance.adjust', $c, $id, ['draft', 'submitted']);
    }

    /** @param list<string> $states */
    private function action(User $u, string $p, int $c, int $id, array $states): bool
    {
        if (! $this->record($u, $p, $c, 'attendance_adjustment_requests', $id) || ! in_array($this->status('attendance_adjustment_requests', $id), $states, true)) {
            return false;
        }
        $record = DB::table('attendance_adjustment_requests')->where('id', $id)->value('attendance_record_id');

        return is_numeric($record) && $this->unlockedAttendanceRecord((int) $record);
    }
}
