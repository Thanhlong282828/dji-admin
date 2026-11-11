<?php

namespace Modules\Leave\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;

/** @property int $id @property int $company_id @property int $employee_id @property int $leave_policy_id @property int $requested_minutes @property int $version @property LeaveRequestStatus $status @property CarbonImmutable $starts_at @property CarbonImmutable $ends_at */
final class LeaveRequest extends Model
{
    protected $fillable = [
        'company_id',
        'days',
        'employee_id',
        'ends_at',
        'idempotency_key',
        'leave_policy_id',
        'leave_type_id',
        'reason',
        'requested_minutes',
        'starts_at',
        'status',
        'timezone',
    ];

    protected $hidden = ['attachment_storage_key'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'status' => LeaveRequestStatus::class, 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime'];
    }
}
