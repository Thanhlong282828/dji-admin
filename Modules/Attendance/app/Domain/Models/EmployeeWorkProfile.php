<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property int $employee_id @property string $timezone @property int $work_calendar_id @property int|null $default_shift_id */
final class EmployeeWorkProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attendance_required' => 'boolean', 'overtime_eligible' => 'boolean', 'leave_eligible' => 'boolean', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
