<?php

namespace Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property int $company_id @property string $starts_at @property string $ends_at @property bool $crosses_midnight @property int $planned_work_minutes @property int $grace_in_minutes @property int $grace_out_minutes @property string $rounding_rule */
final class WorkShift extends Model
{
    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['crosses_midnight' => 'boolean', 'rules' => 'array'];
    }

    /** @return HasMany<ShiftBreakRule, $this> */
    public function breakRules(): HasMany
    {
        return $this->hasMany(ShiftBreakRule::class, 'work_shift_id');
    }
}
