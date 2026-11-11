<?php

namespace Modules\Employee\Domain\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Enums\EmploymentStatus;
use Modules\Employee\Domain\Enums\EmploymentType;
use Modules\Employee\Domain\Enums\Gender;
use Modules\Employee\Infrastructure\Casts\EncryptedIdentityNumberCast;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $employee_code
 * @property CarbonInterface $join_date
 */
final class Employee extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'avatar_storage_key',
        'company_id',
        'employee_code',
        'employment_status',
        'employment_type',
        'full_name',
        'identity_number',
        'identity_number_hash',
        'join_date',
        'manager_id',
        'nationality',
        'official_date',
        'personal_email',
        'phone',
        'source',
        'source_application_id',
        'source_candidate_id',
        'source_offer_id',
        'termination_date',
        'user_id',
        'work_email',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'identity_number' => EncryptedIdentityNumberCast::class,
            'date_of_birth' => 'date', 'identity_expires_at' => 'date',
            'join_date' => 'date', 'official_date' => 'date', 'termination_date' => 'date',
            'addresses' => 'array', 'emergency_contacts' => 'array', 'dependants' => 'array',
            'gender' => Gender::class, 'employment_status' => EmploymentStatus::class,
            'employment_type' => EmploymentType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** @return HasMany<Employee, $this> */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /** @return HasMany<EmployeeAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /** @return HasMany<EmployeeHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    /** @return HasOne<EmployeeLifecycleEvent, $this> */
    public function latestLifecycleEvent(): HasOne
    {
        return $this->hasOne(EmployeeLifecycleEvent::class)->latestOfMany();
    }

    /** @return HasMany<EmployeeCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(EmployeeCredential::class);
    }

    /** @return HasMany<EmploymentContract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    /** @return HasMany<DataConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(DataConsent::class);
    }

    /** @return HasMany<EmployeeDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
