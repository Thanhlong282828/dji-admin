<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property int $employee_id @property int $version @property bool $is_locked */
final class AttendanceDailyRecord extends Model
{
    protected $table = 'attendance_records';

    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['work_date' => 'immutable_date', 'check_in_at' => 'immutable_datetime', 'check_out_at' => 'immutable_datetime', 'anomalies' => 'array', 'is_locked' => 'boolean'];
    }
}
