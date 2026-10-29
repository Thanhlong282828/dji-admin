<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property int $company_id @property string $timezone */
final class WorkCalendar extends Model
{
    protected $fillable = [
        'code',
        'company_id',
        'effective_from',
        'name',
        'timezone',
        'weekly_work_minutes',
    ];

    /** @return HasMany<WorkCalendarDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(WorkCalendarDay::class);
    }
}
