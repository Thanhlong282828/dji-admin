<?php

namespace Modules\Company\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Company\Database\Factories\CompanyFactory;
use Modules\Company\Domain\Enums\CompanyStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'country_code',
        'created_by',
        'currency',
        'currency_code',
        'functional_currency_code',
        'is_active',
        'name',
        'status',
        'tax_code',
        'timezone',
        'updated_by',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $company): void {
            $company->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['status' => CompanyStatus::class, 'is_active' => 'boolean'];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CompanyStatus::ACTIVE->value);
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function organizationUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function branches(): HasMany
    {
        return $this->organizationUnits()->where('type', 'branch');
    }

    /** @return HasMany<OrganizationUnit, $this> */
    public function departments(): HasMany
    {
        return $this->organizationUnits()->where('type', 'department');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('company')->logOnlyDirty()->logAll();
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
