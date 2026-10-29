<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property int $payroll_period_id @property int $snapshot_version */
final class PayrollInputSnapshot extends Model
{
    protected $fillable = [
        'company_id',
        'approved_overtime_minutes',
        'attendance_record_count',
        'blocking_issue_count',
        'employee_count',
        'generated_at',
        'generated_by',
        'idempotency_key',
        'leave_record_count',
        'locked_at',
        'note',
        'overtime_record_count',
        'paid_leave_minutes',
        'payroll_period_id',
        'scheduled_minutes',
        'snapshot_version',
        'status',
        'superseded_at',
        'superseded_by_snapshot_id',
        'unpaid_leave_minutes',
        'validated_at',
        'warning_issue_count',
        'worked_minutes',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'immutable_datetime', 'locked_at' => 'immutable_datetime'];
    }
}
