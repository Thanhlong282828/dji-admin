<?php

namespace Modules\Onboarding\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Employee\Domain\Models\Employee;
use Modules\Onboarding\Domain\Enums\OnboardingStatus;

/** @property int $id */
final class OnboardingCase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => OnboardingStatus::class, 'start_date' => 'date', 'probation_review_date' => 'date', 'checklist' => 'array', 'provisioning' => 'array'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function buddy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'buddy_id');
    }

    /** @return HasMany<OnboardingChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(OnboardingChecklistItem::class);
    }

    /** @return HasMany<OnboardingProvisioningItem, $this> */
    public function provisioningItems(): HasMany
    {
        return $this->hasMany(OnboardingProvisioningItem::class);
    }
}
