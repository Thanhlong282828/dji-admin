<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AttendanceRecordPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'attendance.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'attendance.view', $c, 'attendance_records', $id);
    }

    public function viewOwn(User $u, int $c, int $employee): bool
    {
        return $this->employee($u, 'attendance.view-own', $c, $employee, 'attendance.adjust');
    }

    public function viewOwnRecord(User $u, int $c, int $id): bool
    {
        $employeeId = DB::table('attendance_records')->where('id', $id)->where('company_id', $c)->value('employee_id');
        if (! is_numeric($employeeId)) {
            abort(404);
        }

        return $this->viewOwn($u, $c, (int) $employeeId);
    }

    public function checkIn(User $u, int $c, int $employee): bool
    {
        return $this->capture($u, 'attendance.check-in', $c, $employee);
    }

    public function checkOut(User $u, int $c, int $employee): bool
    {
        return $this->capture($u, 'attendance.check-out', $c, $employee);
    }

    public function submit(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'attendance.adjust', $c, 'attendance_records', $id) && $this->unlockedAttendanceRecord($id) && $this->textValue('attendance_records', $id, 'approval_status') === 'draft';
    }

    public function approve(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'attendance.approve', $c, 'attendance_records', $id) && $this->unlockedAttendanceRecord($id) && $this->textValue('attendance_records', $id, 'approval_status') === 'submitted';
    }

    public function reject(User $u, int $c, int $id): bool
    {
        return $this->approve($u, $c, $id);
    }

    public function export(User $u, int $c): bool
    {
        return $this->company($u, 'attendance.export', $c);
    }

    private function capture(User $u, string $permission, int $company, int $employee): bool
    {
        if ($employee > 0) {
            return $this->employee($u, $permission, $company, $employee, 'attendance.adjust');
        }

        return $this->company($u, $permission, $company) && DB::table('employees')->where('company_id', $company)->where('user_id', $u->id)->exists();
    }
}
