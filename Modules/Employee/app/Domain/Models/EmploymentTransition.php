<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class EmploymentTransition extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
