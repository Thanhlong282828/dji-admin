<?php

namespace Modules\Organization\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Domain\Models\Company;

final class JobPosition extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['career_level' => 'integer', 'approved_headcount' => 'integer', 'budgeted_cost' => 'decimal:2', 'competency_requirements' => 'array', 'is_critical' => 'boolean'];
    }

    /** @return BelongsTo<Company,$this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<OrganizationUnit,$this> */
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    /** @return HasMany<EmployeeAssignment,$this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }
}
