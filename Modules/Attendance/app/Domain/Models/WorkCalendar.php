<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property int $company_id @property string $timezone */
final class WorkCalendar extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<WorkCalendarDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(WorkCalendarDay::class);
    }
}
