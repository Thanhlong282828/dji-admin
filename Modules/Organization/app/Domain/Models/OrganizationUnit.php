<?php

namespace Modules\Organization\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Domain\Models\Company;

final class OrganizationUnit extends Model
{
    protected $fillable = [
        'code',
        'company_id',
        'cost_center_id',
        'effective_from',
        'effective_to',
        'headcount_limit',
        'metadata',
        'name',
        'parent_id',
        'type',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'metadata' => 'array'];
    }

    /** @return BelongsTo<Company,$this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<self,$this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self,$this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<CostCenter,$this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return HasMany<JobPosition,$this> */
    public function positions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }
}
