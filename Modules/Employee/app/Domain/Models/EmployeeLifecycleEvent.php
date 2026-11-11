<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Employee\Domain\Enums\EmployeeLifecycleState;

final class EmployeeLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'correlation_id',
        'created_by',
        'effective_at',
        'employee_id',
        'event_type',
        'from_state',
        'idempotency_key',
        'payload_checksum',
        'to_state',
    ];

    protected function casts(): array
    {
        return ['from_state' => EmployeeLifecycleState::class, 'to_state' => EmployeeLifecycleState::class, 'effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
