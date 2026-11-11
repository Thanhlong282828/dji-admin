<?php

namespace Modules\Attendance\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Employee\Domain\Models\Employee;

/** @property int $id @property int $company_id @property int $employee_id @property CarbonImmutable $occurred_at @property AttendanceEventType $event_type */
final class AttendanceEvent extends Model
{
    protected $fillable = [
        'branch_id',
        'company_id',
        'created_by',
        'deduplication_key',
        'employee_id',
        'event_type',
        'local_date',
        'occurred_at',
        'source',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'local_date' => 'immutable_date', 'event_type' => AttendanceEventType::class, 'metadata' => 'array', 'invalidated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
