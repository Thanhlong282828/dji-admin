<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Employee\Domain\Enums\EmployeeLifecycleState;

final class EmployeeLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_state' => EmployeeLifecycleState::class, 'to_state' => EmployeeLifecycleState::class, 'effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
