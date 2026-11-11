<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property int $employee_id @property string $timezone @property int $work_calendar_id @property int|null $default_shift_id */
final class EmployeeWorkProfile extends Model
{
    protected $fillable = [
        'company_id',
        'daily_minutes',
        'effective_from',
        'employee_id',
        'fte_basis_points',
        'timezone',
        'weekly_minutes',
        'work_calendar_id',
    ];

    protected function casts(): array
    {
        return ['attendance_required' => 'boolean', 'overtime_eligible' => 'boolean', 'leave_eligible' => 'boolean', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
