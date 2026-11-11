<?php

namespace Modules\Organization\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Domain\Models\Company;

final class CostCenter extends Model
{
    protected $fillable = [
        'annual_budget',
        'code',
        'company_id',
        'currency',
        'name',
    ];

    protected function casts(): array
    {
        return ['annual_budget' => 'decimal:2'];
    }

    /** @return BelongsTo<Company,$this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<OrganizationUnit,$this> */
    public function organizationUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }
}
