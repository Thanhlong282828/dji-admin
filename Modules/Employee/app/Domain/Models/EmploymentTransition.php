<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class EmploymentTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'approved_by',
        'checksum',
        'company_id',
        'effective_at',
        'employee_id',
        'from_state',
        'reason',
        'to_state',
        'transition_type',
    ];

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
