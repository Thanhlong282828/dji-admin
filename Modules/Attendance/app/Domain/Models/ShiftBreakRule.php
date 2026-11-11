<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class ShiftBreakRule extends Model
{
    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean'];
    }
}
