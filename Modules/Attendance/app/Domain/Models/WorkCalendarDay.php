<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class WorkCalendarDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_working_day' => 'boolean'];
    }
}
