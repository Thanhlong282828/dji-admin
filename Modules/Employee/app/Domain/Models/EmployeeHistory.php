<?php

namespace Modules\Employee\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employee\Domain\Enums\EmployeeHistoryEventType;

final class EmployeeHistory extends Model
{
    protected $fillable = [
        'after',
        'approved_by',
        'before',
        'effective_at',
        'employee_id',
        'event_type',
        'reason',
    ];

    protected function casts(): array
    {
        return ['event_type' => EmployeeHistoryEventType::class, 'before' => 'array', 'after' => 'array', 'effective_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
